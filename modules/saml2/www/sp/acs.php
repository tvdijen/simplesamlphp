<?php

/**
 * Assertion consumer service handler for SAML 2.0 SP authentication client.
 */

$b = SAML2_Binding::getCurrentBinding();
$response = $b->receive();
if (!($response instanceof SAML2_Response)) {
	throw new SimpleSAML_Error_BadRequest('Invalid message received to AssertionConsumerService endpoint.');
}

$relayState = $response->getRelayState();
if (empty($relayState)) {
	throw new SimpleSAML_Error_BadRequest('Missing relaystate in message received on AssertionConsumerService endpoint.');
}

$state = SimpleSAML_Auth_State::loadState($relayState, sspmod_saml2_Auth_Source_SP::STAGE_SENT);

/* Find authentication source. */
assert('array_key_exists(sspmod_saml2_Auth_Source_SP::AUTHID, $state)');
$sourceId = $state[sspmod_saml2_Auth_Source_SP::AUTHID];

$source = SimpleSAML_Auth_Source::getById($sourceId);
if ($source === NULL) {
	throw new Exception('Could not find authentication source with id ' . $sourceId);
}

$idp = $response->getIssuer();
if ($idp === NULL) {
	throw new Exception('Missing <saml:Issuer> in message delivered to AssertionConsumerService.');
}

$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$idpMetadata = $metadata->getMetaDataConfig($idp, 'saml20-idp-remote');
$spMetadata = $metadata->getMetaDataConfig($source->getEntityId(), 'saml20-sp-hosted');

/* Check if the IdP is allowed to authenticate users for this authentication source. */
if (!$source->isIdPValid($idp)) {
	throw new Exception('Invalid IdP responded for authentication source with id ' . $sourceId .
		'. The IdP was ' . var_export($idp, TRUE));
}


try {
	$assertion = sspmod_saml2_Message::processResponse($spMetadata, $idpMetadata, $response);
} catch (sspmod_saml2_Error $e) {
	/* The status of the response wasn't "success". */
	$e = $e->toException();
	SimpleSAML_Auth_State::throwException($state, $e);
}

$nameId = $assertion->getNameID();
$sessionIndex = $assertion->getSessionIndex();

/* We need to save the NameID and SessionIndex for logout. */
$logoutState = array(
	sspmod_saml2_Auth_Source_SP::LOGOUT_IDP => $idp,
	sspmod_saml2_Auth_Source_SP::LOGOUT_NAMEID => $nameId,
	sspmod_saml2_Auth_Source_SP::LOGOUT_SESSIONINDEX => $sessionIndex,
	);
$state['LogoutState'] = $logoutState;

$source->onLogin($idp, $state);

$state['Attributes'] = $assertion->getAttributes();
SimpleSAML_Auth_Source::completeAuth($state);

?>