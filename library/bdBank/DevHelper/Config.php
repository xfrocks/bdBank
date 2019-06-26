<?php

class bdBank_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array(
        'transaction' => array(
            'name' => 'transaction',
            'camelCase' => 'Transaction',
            'camelCasePlural' => 'Transactions',
            'camelCaseWSpace' => 'Transaction',
            'camelCasePluralWSpace' => 'Transactions',
            'fields' => array(
                'transaction_id' => array('name' => 'transaction_id', 'type' => 'uint', 'autoIncrement' => true),
                'from_user_id' => array('name' => 'from_user_id', 'type' => 'uint', 'required' => true),
                'to_user_id' => array('name' => 'to_user_id', 'type' => 'uint', 'required' => true),
                'amount' => array('name' => 'amount', 'type' => 'money', 'required' => true),
                'tax_amount' => array('name' => 'tax_amount', 'type' => 'money', 'default' => 0),
                'comment' => array('name' => 'comment', 'type' => 'string', 'length' => 255),
                'transaction_type' => array('name' => 'transaction_type', 'type' => 'uint', 'default' => 0),
                'transfered' => array('name' => 'transfered', 'type' => 'uint', 'required' => true),
                'reversed' => array('name' => 'reversed', 'type' => 'uint', 'default' => 0),
            ),
            'phrases' => array(),
            'id_field' => 'transaction_id',
            'title_field' => 'comment',
            'primaryKey' => array('transaction_id'),
            'indeces' => array(
                'comment' => array('name' => 'comment', 'fields' => array('comment'), 'type' => 'NORMAL'),
                'from_user_id' => array(
                    'name' => 'from_user_id',
                    'fields' => array('from_user_id'),
                    'type' => 'NORMAL'
                ),
                'to_user_id' => array('name' => 'to_user_id', 'fields' => array('to_user_id'), 'type' => 'NORMAL'),
            ),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false
            ),
        ),
        'attachment_downloaded' => array(
            'name' => 'attachment_downloaded',
            'camelCase' => 'AttachmentDownloaded',
            'camelCasePlural' => 'DownloadedAttachments',
            'camelCaseWSpace' => 'Attachment Downloaded',
            'camelCasePluralWSpace' => 'Downloaded Attachments',
            'fields' => array(
                'attachment_id' => array('name' => 'attachment_id', 'type' => 'uint', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'download_date' => array('name' => 'download_date', 'type' => 'uint', 'required' => true),
            ),
            'phrases' => array(),
            'id_field' => 'attachment_id',
            'title_field' => false,
            'primaryKey' => array('attachment_id', 'user_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false
            ),
        ),
        'archive' => array(
            'name' => 'archive',
            'camelCase' => 'Archive',
            'camelCasePlural' => 'Archives',
            'camelCaseWSpace' => 'Archive',
            'camelCasePluralWSpace' => 'Archives',
            'fields' => array(
                'transaction_id' => array('name' => 'transaction_id', 'type' => 'uint', 'required' => true),
                'from_user_id' => array('name' => 'from_user_id', 'type' => 'uint', 'required' => true),
                'to_user_id' => array('name' => 'to_user_id', 'type' => 'uint', 'required' => true),
                'amount' => array('name' => 'amount', 'type' => 'money', 'required' => true),
                'tax_amount' => array('name' => 'tax_amount', 'type' => 'money', 'default' => 0),
                'comment' => array('name' => 'comment', 'type' => 'string', 'length' => 255),
                'transaction_type' => array('name' => 'transaction_type', 'type' => 'uint', 'default' => 0),
                'transfered' => array('name' => 'transfered', 'type' => 'uint', 'required' => true),
            ),
            'phrases' => array(),
            'id_field' => 'transaction_id',
            'title_field' => 'comment',
            'primaryKey' => array('transaction_id'),
            'indeces' => array(
                'comment' => array('name' => 'comment', 'fields' => array('comment'), 'type' => 'NORMAL'),
            ),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false
            ),
        ),
        'stats' => array(
            'name' => 'stats',
            'camelCase' => 'Stats',
            'camelCasePlural' => false,
            'camelCaseWSpace' => 'Stats',
            'camelCasePluralWSpace' => false,
            'fields' => array(
                'stats_key' => array('name' => 'stats_key', 'type' => 'string', 'length' => 245),
                'stats_date' => array('name' => 'stats_date', 'type' => 'string', 'length' => 245),
                'stats_value' => array('name' => 'stats_value', 'type' => 'serialized'),
                'rebuild_date' => array('name' => 'rebuild_date', 'type' => 'uint', 'required' => true),
            ),
            'phrases' => array(),
            'id_field' => 'stats_key',
            'title_field' => 'stats_key',
            'primaryKey' => false,
            'indeces' => array(),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false
            ),
        ),
        'credit' => array(
            'name' => 'credit',
            'camelCase' => 'Credit',
            'camelCasePlural' => false,
            'camelCaseWSpace' => 'Credit',
            'camelCasePluralWSpace' => false,
            'fields' => array(
                'credit_id' => array('name' => 'credit_id', 'type' => 'uint', 'required' => true, 'autoIncrement' => true),
                'transaction_id' => array('name' => 'transaction_id', 'type' => 'uint', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'amount' => array('name' => 'amount', 'type' => 'money', 'required' => true),
                'credit_date' => array('name' => 'credit_date', 'type' => 'uint', 'required' => true),
            ),
            'phrases' => array(),
            'title_field' => false,
            'primaryKey' => array('credit_id'),
            'indeces' => array(
                'transaction_id' => array('name' => 'transaction_id', 'fields' => array('transaction_id'), 'type' => 'NORMAL'),
                'user_id' => array('name' => 'user_id', 'fields' => array('user_id'), 'type' => 'NORMAL'),
            ),
            'files' => array('data_writer' => false, 'model' => false, 'route_prefix_admin' => false, 'controller_admin' => false),
        ),
    );
    protected $_dataPatches = array(
        'xf_user' => array(
            'bdbank_money' => array('name' => 'bdbank_money', 'type' => 'money', 'default' => 0),
            'bdbank_credit' => array('name' => 'bdbank_credit', 'type' => 'money', 'default' => 0),
        ),
        'xf_forum' => array(
            'bdbank_options' => array('name' => 'bdbank_options', 'type' => 'serialized'),
        ),
        'xf_user_option' => array(
            'bdbank_show_money' => array('name' => 'bdbank_show_money', 'type' => 'uint', 'default' => 1),
        ),
        'xf_bdbank_transaction' => array(
            'index::from_user_id' => array(
                'index' => true,
                'type' => 'NORMAL',
                'fields' => array('from_user_id'),
                'name' => 'from_user_id'
            ),
            'index::to_user_id' => array(
                'index' => true,
                'type' => 'NORMAL',
                'fields' => array('to_user_id'),
                'name' => 'to_user_id'
            ),
        ),
    );
    protected $_exportPath = '/Users/sondh/XenForo/bdBank';
    protected $_exportIncludes = array();
    protected $_exportExcludes = array();
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
     **/
    protected function _upgrade()
    {
        return true; // remove this line to trigger update

        /*
        $this->addDataClass(
            'name_here',
            array( // fields
                'field_here' => array(
                    'type' => 'type_here',
                    // 'length' => 'length_here',
                    // 'required' => true,
                    // 'allowedValues' => array('value_1', 'value_2'),
                    // 'default' => 0,
                    // 'autoIncrement' => true,
                ),
                // other fields go here
            ),
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
            array( // indeces
                array(
                    'fields' => array('field_1', 'field_2'),
                    'type' => 'NORMAL', // UNIQUE or FULLTEXT
                ),
            ),
        );
        */
    }
}
