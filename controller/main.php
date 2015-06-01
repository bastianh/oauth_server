<?php
/**
 *
 * @package phpBB Extension - OAuth Server
 * @copyright (c) 2015 Bastian Hoyer
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */
namespace dafire\oauth_server\controller;

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
    protected $request;


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

        $this->server = new \dafire\oauth_server\lib\server($db, $table_prefix);
    }


    public function authorize()
    {

        $request = new \OAuth2\Request(
            $this->request->get_super_global(\phpbb\request\request_interface::GET),
            $this->request->get_super_global(\phpbb\request\request_interface::POST),
            array(),
            $this->request->get_super_global(\phpbb\request\request_interface::COOKIE),
            $this->request->get_super_global(\phpbb\request\request_interface::FILES),
            $this->request->get_super_global(\phpbb\request\request_interface::SERVER)
        );
        $response = new \OAuth2\Response();

        // validate the authorize request
        if (!$this->server->validateAuthorizeRequest($request, $response)) {
            $response->send();
            die;
        }

        var_dump($this->server);

        return $this->helper->render('demo_body.html', "seite");
    }
}
