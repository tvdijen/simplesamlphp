<?php

/**
 * This page shows a username/password login form, and passes information from it
 * to the \SimpleSAML\Module\core\Auth\UserPassBase class, which is a generic class for
 * username/password authentication.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */

// Retrieve the authentication state
if (!array_key_exists('AuthState', $_REQUEST)) {
    throw new \SimpleSAML\Error\BadRequest('Missing AuthState parameter.');
}
$authStateId = $_REQUEST['AuthState'];
$state = \SimpleSAML\Auth\State::loadState($authStateId, \SimpleSAML\Module\core\Auth\UserPassBase::STAGEID);

$source = \SimpleSAML\Auth\Source::getById($state[\SimpleSAML\Module\core\Auth\UserPassBase::AUTHID]);
if ($source === null) {
    throw new \Exception('Could not find authentication source with id '.$state[\SimpleSAML\Module\core\Auth\UserPassBase::AUTHID]);
}


if (array_key_exists('username', $_REQUEST)) {
    $username = $_REQUEST['username'];
} elseif ($source->getRememberUsernameEnabled() && array_key_exists($source->getAuthId().'-username', $_COOKIE)) {
    $username = $_COOKIE[$source->getAuthId() . '-username'];
} elseif (isset($state['core:username'])) {
    $username = (string) $state['core:username'];
} else {
    $username = '';
}

if (array_key_exists('password', $_REQUEST)) {
    $password = $_REQUEST['password'];
} else {
    $password = '';
}

$errorCode = null;
$errorParams = null;

if (!empty($_REQUEST['username']) || !empty($password)) {
    // Either username or password set - attempt to log in

    if (array_key_exists('forcedUsername', $state)) {
        $username = $state['forcedUsername'];
    }

    if ($source->getRememberUsernameEnabled()) {
        $sessionHandler = \SimpleSAML\SessionHandler::getSessionHandler();
        $params = $sessionHandler->getCookieParams();
        $params['expire'] = time();
        $params['expire'] += (isset($_REQUEST['remember_username']) && $_REQUEST['remember_username'] == 'Yes' ? 31536000 : -300);
        \SimpleSAML\Utils\HTTP::setCookie($source->getAuthId().'-username', $username, $params, false);
    }

    if ($source->isRememberMeEnabled()) {
        if (array_key_exists('remember_me', $_REQUEST) && $_REQUEST['remember_me'] === 'Yes') {
            $state['RememberMe'] = true;
            $authStateId = \SimpleSAML\Auth\State::saveState($state, \SimpleSAML\Module\core\Auth\UserPassBase::STAGEID);
        }
    }

    try {
        \SimpleSAML\Module\core\Auth\UserPassBase::handleLogin($authStateId, $username, $password);
    } catch (\SimpleSAML\Error\Error $e) {
        /* Login failed. Extract error code and parameters, to display the error. */
        $errorCode = $e->getErrorCode();
        $errorParams = $e->getParameters();
    }
}

$globalConfig = \SimpleSAML\Configuration::getInstance();
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'core:loginuserpass.php');
$t->data['stateparams'] = array('AuthState' => $authStateId);
if (array_key_exists('forcedUsername', $state)) {
    $t->data['username'] = $state['forcedUsername'];
    $t->data['forceUsername'] = true;
    $t->data['rememberUsernameEnabled'] = false;
    $t->data['rememberUsernameChecked'] = false;
    $t->data['rememberMeEnabled'] = $source->isRememberMeEnabled();
    $t->data['rememberMeChecked'] = $source->isRememberMeChecked();
} else {
    $t->data['username'] = $username;
    $t->data['forceUsername'] = false;
    $t->data['rememberUsernameEnabled'] = $source->getRememberUsernameEnabled();
    $t->data['rememberUsernameChecked'] = $source->getRememberUsernameChecked();
    $t->data['rememberMeEnabled'] = $source->isRememberMeEnabled();
    $t->data['rememberMeChecked'] = $source->isRememberMeChecked();
    if (isset($_COOKIE[$source->getAuthId().'-username'])) {
        $t->data['rememberUsernameChecked'] = true;
    }
}
$t->data['links'] = $source->getLoginLinks();
$t->data['errorcode'] = $errorCode;
$t->data['errorcodes'] = SimpleSAML\Error\ErrorCodes::getAllErrorCodeMessages();
$t->data['errorparams'] = $errorParams;

if (isset($state['SPMetadata'])) {
    $t->data['SPMetadata'] = $state['SPMetadata'];
} else {
    $t->data['SPMetadata'] = null;
}

$t->show();
exit();
