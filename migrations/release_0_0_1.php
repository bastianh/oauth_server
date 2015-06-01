<?php

namespace dafire\oauth_server\migrations;

use phpbb\db\migration\migration;

class release_0_0_1 extends migration
{

    static public function depends_on()
    {
        return array('\phpbb\db\migration\data\v310\dev');
    }

    public function update_schema()
    {
        return array(
            'add_tables' => array(
                $this->table_prefix . 'oauth_server_clients' => array(
                    'COLUMNS' => array(
                        'client_id' => array('VCHAR:100', NULL),
                        'redirect_uri' => array('VCHAR_UNI', ''),
                        'scope' => array('VCHAR_UNI', ''),
                    ),
                    'PRIMARY_KEY' => 'client_id',
                    'KEYS' => array(
                    ),
                )
            )
        );
    }

    public function revert_schema()
    {
        return array(
            'drop_tables' => array(
                $this->table_prefix . 'oauth_server_clients'
            ),
        );
    }
}
