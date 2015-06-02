<?php
/**
 *
 * @package phpBB Extension - OAuth Server
 * @copyright (c) 2015 Bastian Hoyer
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */
namespace dafire\oauth_server\controller;

use dafire\oauth_server\lib\phpbb_storage;

class main
{
    /* @var \phpbb\config\config */
    protected $config;
    /* @var \phpbb\controller\helper */
    protected $helper;
    /* @var \phpbb\template\template */
    protected $template;
    /* @var \phpbb\user */
    protected $user;

    protected $server;


    /**
     * Constructor
     *
     * @param \phpbb\config\config $config
     * @param \phpbb\controller\helper $helper
     * @param \phpbb\template\template $template
     * @param \phpbb\user $user
     */
    public function __construct(\phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\template\template $template, \phpbb\user $user, \phpbb\request\request $request, \phpbb\db\driver\driver_interface $db, $table_prefix)
    {
        $this->config = $config;
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->request = $request;

        $storage = new phpbb_storage($db, $table_prefix);
        $this->server = new \OAuth2\Server($storage, array('enforce_state' => false));


        $this->oauth_request = new \OAuth2\Request(
            $request->get_super_global(\phpbb\request\request_interface::GET),
            $request->get_super_global(\phpbb\request\request_interface::POST),
            array(),
            $request->get_super_global(\phpbb\request\request_interface::COOKIE),
            $request->get_super_global(\phpbb\request\request_interface::FILES),
            $request->get_super_global(\phpbb\request\request_interface::SERVER)
        );

    }

    public function route($route)
    {
        if ($route == "authorize") {
            return $this->authorize();
        } else if ($route == "token") {
            $this->token();
            exit();
        } else if ($route == "me") {
            $this->me();
            exit();
        }
        return null;
    }

    public function token()
    {
        $this->server->handleTokenRequest($this->oauth_request)->send();
        exit();
    }

    public function authorize()
    {
        $post = $this->request->get_super_global(\phpbb\request\request_interface::POST);
        $current_uri = $this->request->get_super_global(\phpbb\request\request_interface::SERVER)['REQUEST_URI'];

        $response = new \OAuth2\Response();

        // validate the authorize request
        if (!$this->server->validateAuthorizeRequest($this->oauth_request, $response)) {
            $response->send();
            exit();
        }

        if (empty($post)) {
            $this->template->assign_var('CURRENT_URI', $current_uri);
            return $this->helper->render('oauth_server_authorize.html', "authorize");
        }


        $is_authorized = ($post['authorized'] === 'yes');
        $this->server->handleAuthorizeRequest($this->oauth_request, $response, $is_authorized);
        $response->send();
        exit();
    }

    public function me()
    {
        if (!$this->server->verifyResourceRequest($this->oauth_request)) {
            $this->server->getResponse()->send();
            die();
        }
        echo json_encode(array('success' => true, 'message' => 'You accessed my APIs!'));
        die();
    }

}
