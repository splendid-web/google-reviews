<?php

namespace splendidweb\googlereviews\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use splendidweb\googlereviews\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class OauthController extends Controller
{
    protected array|bool|int $allowAnonymous = ['start', 'callback'];

    private const STATE_SESSION_KEY = 'google-reviews.oauth.state';
    private const REDIRECT_SESSION_KEY = 'google-reviews.oauth.redirect';

    /**
     * Starts the Google OAuth authorization flow.
     */
    public function actionStart(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('accessCp');

        $settings = Plugin::getInstance()->getSettings();
        $clientId = trim($settings->getParsedOAuthClientId());

        if ($clientId === '') {
            Craft::$app->getSession()->setError('Set GBP API OAuth Client ID before connecting Google.');
            return $this->redirect($this->getReturnUrl());
        }

        $state = Craft::$app->getSecurity()->generateRandomString(32);
        $session = Craft::$app->getSession();
        $session->set(self::STATE_SESSION_KEY, $state);
        $session->set(self::REDIRECT_SESSION_KEY, $this->getReturnUrl());

        $redirectUri = UrlHelper::actionUrl('google-reviews/oauth/callback');
        $query = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/business.manage',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        return $this->redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($query));
    }

    /**
     * Handles Google OAuth callback and stores refresh token when returned.
     *
     * @throws BadRequestHttpException
     */
    public function actionCallback(): Response
    {
        $session = Craft::$app->getSession();
        $expectedState = (string)$session->get(self::STATE_SESSION_KEY, '');
        $redirect = (string)$session->get(self::REDIRECT_SESSION_KEY, $this->getReturnUrl());
        $session->remove(self::STATE_SESSION_KEY);
        $session->remove(self::REDIRECT_SESSION_KEY);

        $state = trim((string)$this->request->getRequiredQueryParam('state'));
        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new BadRequestHttpException('OAuth state did not match. Please try connecting again.');
        }

        $error = trim((string)$this->request->getQueryParam('error'));
        if ($error !== '') {
            $session->setError('Google authorization failed: ' . $error);
            return $this->redirect($redirect);
        }

        $code = trim((string)$this->request->getRequiredQueryParam('code'));
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $clientId = trim($settings->getParsedOAuthClientId());
        $clientSecret = trim($settings->getParsedOAuthClientSecret());

        if ($clientId === '' || $clientSecret === '') {
            $session->setError('GBP API OAuth Client ID and Secret are required before completing OAuth.');
            return $this->redirect($redirect);
        }

        $redirectUri = UrlHelper::actionUrl('google-reviews/oauth/callback');

        try {
            $tokens = $plugin->get('sync')->exchangeAuthorizationCode($clientId, $clientSecret, $code, $redirectUri);
            $refreshToken = trim((string)($tokens['refreshToken'] ?? ''));

            if ($refreshToken === '') {
                $session->setError('Google did not return a refresh token. Revoke app access and try again.');
                return $this->redirect($redirect);
            }

            if (str_starts_with(trim($settings->oauthRefreshToken), '$')) {
                $session->setNotice('Google connected. Copy this refresh token into your env var: ' . $refreshToken);
                return $this->redirect($redirect);
            }

            $settings->oauthRefreshToken = $refreshToken;
            if (!$plugin->saveSettings($settings->toArray())) {
                $errors = implode('; ', $settings->getErrorSummary(true));
                $session->setError('Google connected but plugin settings could not be saved: ' . $errors);
                return $this->redirect($redirect);
            }

            $session->setNotice('Google connected successfully. Refresh token has been saved.');
        } catch (\Throwable $exception) {
            $session->setError('Google connection failed: ' . $exception->getMessage());
        }

        return $this->redirect($redirect);
    }

    /**
     * Tests whether configured OAuth credentials can mint an access token.
     */
    public function actionTest(): Response
    {
        $this->requireCpRequest();
        $this->requirePermission('accessCp');

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $session = Craft::$app->getSession();

        $clientId = trim($settings->getParsedOAuthClientId());
        $clientSecret = trim($settings->getParsedOAuthClientSecret());
        $refreshToken = trim($settings->getParsedOAuthRefreshToken());

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            $session->setError('Set GBP API OAuth Client ID, Client Secret, and Refresh Token before testing.');
            return $this->redirect($this->getReturnUrl());
        }

        try {
            $plugin->get('sync')->fetchAccessToken($clientId, $clientSecret, $refreshToken);
            $session->setNotice('OAuth test passed. Access token exchange succeeded.');
        } catch (\Throwable $exception) {
            $session->setError('OAuth test failed: ' . $exception->getMessage());
        }

        return $this->redirect($this->getReturnUrl());
    }

    private function getReturnUrl(): string
    {
        $returnUrl = trim((string)$this->request->getQueryParam('return'));
        return $returnUrl !== '' ? $returnUrl : UrlHelper::cpUrl('settings/plugins/google-reviews');
    }
}
