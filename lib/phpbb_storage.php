<?php
/**
 * Created by IntelliJ IDEA.
 * User: bastianh
 * Date: 01.06.15
 * Time: 16:57
 */

namespace dafire\oauth_server\lib;


use OAuth2\Storage\AccessTokenInterface;
use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\Storage\ClientCredentialsInterface;
use OAuth2\Storage\ClientInterface;

class phpbb_storage implements
    ClientInterface,
    AuthorizationCodeInterface,
    ClientCredentialsInterface,
    AccessTokenInterface
{
    function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->db = $db;
        $this->table_prefix = $table_prefix;
    }


    /**
     * Get client details corresponding client_id.
     *
     * OAuth says we should store request URIs for each registered client.
     * Implement this function to grab the stored URI for a given client id.
     *
     * @param $client_id
     * Client identifier to be check with.
     *
     * @return array
     *               Client details. The only mandatory key in the array is "redirect_uri".
     *               This function MUST return FALSE if the given client does not exist or is
     *               invalid. "redirect_uri" can be space-delimited to allow for multiple valid uris.
     *               <code>
     *               return array(
     *               "redirect_uri" => REDIRECT_URI,      // REQUIRED redirect_uri registered for the client
     *               "client_id"    => CLIENT_ID,         // OPTIONAL the client id
     *               "grant_types"  => GRANT_TYPES,       // OPTIONAL an array of restricted grant types
     *               "user_id"      => USER_ID,           // OPTIONAL the user identifier associated with this client
     *               "scope"        => SCOPE,             // OPTIONAL the scopes allowed for this client
     *               );
     *               </code>
     *
     * @ingroup oauth2_section_4
     */
    public function getClientDetails($client_id)
    {

        $sql = 'SELECT * FROM ' . $this->table_prefix .
            'oauth_server_clients WHERE client_id = \'' . $this->db->sql_escape($client_id) . '\'';
        $result = $this->db->sql_query($sql);
        $data = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        return $data;
    }

    /**
     * Get the scope associated with this client
     *
     * @return
     * STRING the space-delineated scope list for the specified client_id
     */
    public function getClientScope($client_id)
    {
        if (!$clientDetails = $this->getClientDetails($client_id)) {
            return false;
        };

        if (isset($clientDetails['scope'])) {
            return $clientDetails['scope'];
        }

        return null;
    }

    /**
     * Check restricted grant types of corresponding client identifier.
     *
     * If you want to restrict clients to certain grant types, override this
     * function.
     *
     * @param $client_id
     * Client identifier to be check with.
     * @param $grant_type
     * Grant type to be check with
     *
     * @return
     * TRUE if the grant type is supported by this client identifier, and
     * FALSE if it isn't.
     *
     * @ingroup oauth2_section_4
     */
    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        if ($grant_type == "authorization_code")
            return true;
        return false;
    }

    /**
     * Fetch authorization code data (probably the most common grant type).
     *
     * Retrieve the stored data for the given authorization code.
     *
     * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
     *
     * @param $code
     * Authorization code to be check with.
     *
     * @return
     * An associative array as below, and NULL if the code is invalid
     * @code
     * return array(
     *     "client_id"    => CLIENT_ID,      // REQUIRED Stored client identifier
     *     "user_id"      => USER_ID,        // REQUIRED Stored user identifier
     *     "expires"      => EXPIRES,        // REQUIRED Stored expiration in unix timestamp
     *     "redirect_uri" => REDIRECT_URI,   // REQUIRED Stored redirect URI
     *     "scope"        => SCOPE,          // OPTIONAL Stored scope values in space-separated string
     * );
     * @endcode
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4.1
     *
     * @ingroup oauth2_section_4
     */
    public function getAuthorizationCode($code)
    {

        $sql = 'SELECT * FROM ' . $this->table_prefix .
            'oauth_server_authorization_codes WHERE authorization_code = \'' . $this->db->sql_escape($code) . '\'';
        $result = $this->db->sql_query($sql);

        $data = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        error_log("GET AUTH");
        error_log(print_r($data, 1));
        return $data;

    }

    /**
     * Take the provided authorization code values and store them somewhere.
     *
     * This function should be the storage counterpart to getAuthCode().
     *
     * If storage fails for some reason, we're not currently checking for
     * any sort of success/failure, so you should bail out of the script
     * and provide a descriptive fail message.
     *
     * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
     *
     * @param string $code Authorization code to be stored.
     * @param mixed $client_id Client identifier to be stored.
     * @param mixed $user_id User identifier to be stored.
     * @param string $redirect_uri Redirect URI(s) to be stored in a space-separated string.
     * @param int $expires Expiration to be stored as a Unix timestamp.
     * @param string $scope OPTIONAL Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_4
     */
    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        if (func_num_args() > 6) {
            return call_user_func_array(array($this, 'setAuthorizationCodeWithIdToken'), func_get_args());
        }

        // if it exists, update it.
        if ($this->getAuthorizationCode($code)) {
            $sql = 'UPDATE ' . $this->table_prefix . 'oauth_server_authorization_codes ' .
                "SET client_id='" . $this->db->sql_escape($client_id) . "', " .
                "SET user_id='" . $this->db->sql_escape($user_id) . "', " .
                "SET redirect_uri='" . $this->db->sql_escape($redirect_uri) . "', " .
                "SET expires=" . (int)$expires . ", " .
                'WHERE authorization_code = \'' . $this->db->sql_escape($code) . '\'';
            $this->db->sql_query($sql);
        } else {
            $sql = 'INSERT INTO ' . $this->table_prefix . 'oauth_server_authorization_codes ' .
                '(authorization_code, client_id, user_id, redirect_uri, expires, scope) ' .
                "VALUES ('" . $this->db->sql_escape($code) . "', '" . $this->db->sql_escape($client_id) .
                "', '" . $this->db->sql_escape($user_id) . "', '" . $this->db->sql_escape($redirect_uri) .
                "', " . (int)$expires . ", '" . $this->db->sql_escape($scope) . "')";
            $this->db->sql_query($sql);
        }

        return true;
    }

    private function setAuthorizationCodeWithIdToken($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {

        // if it exists, update it.
        if ($this->getAuthorizationCode($code)) {
            $stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET client_id=:client_id, user_id=:user_id, redirect_uri=:redirect_uri, expires=:expires, scope=:scope, id_token =:id_token where authorization_code=:code', $this->config['code_table']));
        } else {
            $stmt = $this->db->prepare(sprintf('INSERT INTO %s (authorization_code, client_id, user_id, redirect_uri, expires, scope, id_token) VALUES (:code, :client_id, :user_id, :redirect_uri, :expires, :scope, :id_token)', $this->config['code_table']));
        }

        return $stmt->execute(compact('code', 'client_id', 'user_id', 'redirect_uri', 'expires', 'scope', 'id_token'));
    }

    /**
     * once an Authorization Code is used, it must be exipired
     *
     * @see http://tools.ietf.org/html/rfc6749#section-4.1.2
     *
     *    The client MUST NOT use the authorization code
     *    more than once.  If an authorization code is used more than
     *    once, the authorization server MUST deny the request and SHOULD
     *    revoke (when possible) all tokens previously issued based on
     *    that authorization code
     *
     */
    public function expireAuthorizationCode($code)
    {
        $sql = 'DELETE FROM ' . $this->table_prefix . 'oauth_server_authorization_codes WHERE authorization_code = \'' . $this->db->sql_escape($code) . '\'';
        $this->db->sql_query($sql);
        return true;
    }

    /**
     * Make sure that the client credentials is valid.
     *
     * @param $client_id
     * Client identifier to be check with.
     * @param $client_secret
     * (optional) If a secret is required, check that they've given the right one.
     *
     * @return
     * TRUE if the client credentials are valid, and MUST return FALSE if it isn't.
     * @endcode
     *
     * @see http://tools.ietf.org/html/rfc6749#section-3.1
     *
     * @ingroup oauth2_section_3
     */
    public function checkClientCredentials($client_id, $client_secret = null)
    {
        if (!$result = $this->getClientDetails($client_id)) {
            return false;
        };

        return $result && $result['client_secret'] == $client_secret;
    }

    /**
     * Determine if the client is a "public" client, and therefore
     * does not require passing credentials for certain grant types
     *
     * @param $client_id
     * Client identifier to be check with.
     *
     * @return
     * TRUE if the client is public, and FALSE if it isn't.
     * @endcode
     *
     * @see http://tools.ietf.org/html/rfc6749#section-2.3
     * @see https://github.com/bshaffer/oauth2-server-php/issues/257
     *
     * @ingroup oauth2_section_2
     */
    public function isPublicClient($client_id)
    {
        if (!$result = $this->getClientDetails($client_id)) {
            return false;
        };
        return empty($result['client_secret']);
    }

    /**
     * Look up the supplied oauth_token from storage.
     *
     * We need to retrieve access token data as we create and verify tokens.
     *
     * @param $oauth_token
     * oauth_token to be check with.
     *
     * @return
     * An associative array as below, and return NULL if the supplied oauth_token
     * is invalid:
     * - expires: Stored expiration in unix timestamp.
     * - client_id: (optional) Stored client identifier.
     * - user_id: (optional) Stored user identifier.
     * - scope: (optional) Stored scope values in space-separated string.
     * - id_token: (optional) Stored id_token (if "use_openid_connect" is true).
     *
     * @ingroup oauth2_section_7
     */
    public function getAccessToken($oauth_token)
    {
        $sql = 'SELECT * FROM ' . $this->table_prefix .
            'oauth_server_access_tokens WHERE access_token = \'' . $this->db->sql_escape($oauth_token) . '\'';
        $result = $this->db->sql_query($sql);

        $data = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);
        error_log("GET getAccessToken");
        error_log(print_r($data, 1));
        return $data;
    }

    /**
     * Store the supplied access token values to storage.
     *
     * We need to store access token data as we create and verify tokens.
     *
     * @param $oauth_token    oauth_token to be stored.
     * @param $client_id      client identifier to be stored.
     * @param $user_id        user identifier to be stored.
     * @param int $expires expiration to be stored as a Unix timestamp.
     * @param string $scope OPTIONAL Scopes to be stored in space-separated string.
     *
     * @ingroup oauth2_section_4
     */
    public function setAccessToken($oauth_token, $client_id, $user_id, $expires, $scope = null)
    {
        // if it exists, update it.
        if ($this->getAccessToken($oauth_token)) {
            $sql = 'UPDATE ' . $this->table_prefix . 'oauth_server_access_tokens ' .
                "SET client_id='" . $this->db->sql_escape($client_id) . "', " .
                "SET user_id='" . $this->db->sql_escape($user_id) . "', " .
                "SET expires=" . (int)$expires . ", " .
                "SET scope='" . $this->db->sql_escape($scope) . "', " .
                'WHERE access_token = \'' . $this->db->sql_escape($oauth_token) . '\'';
            $this->db->sql_query($sql);
        } else {
            $sql = 'INSERT INTO ' . $this->table_prefix . 'oauth_server_access_tokens ' .
                '(access_token, client_id, expires, user_id, scope) ' .
                "VALUES ('" . $this->db->sql_escape($oauth_token) . "', '" . $this->db->sql_escape($client_id) .
                "', " . (int)$expires .", '" . $this->db->sql_escape($user_id) . "', '" .
                $this->db->sql_escape($scope) . "')";
            $this->db->sql_query($sql);
        }

        return true;
    }
}
