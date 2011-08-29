<?php

require_once dirname(__FILE__) . '/spamd.php';

/**
 * Sam storage implementation for FTP access to the users' user_prefs files.
 *
 * Optional preferences:<pre>
 *   'hostspec'      The hostname of the FTP server.
 *   'port'          The port that the FTP server listens on.
 *   'user_prefs'    The file with the user preferences, relative to the home
 *                   directory. DEFAULT: '.spamassassin/user_prefs'</pre>
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chris Bowlby <cbowlby@tenthpowertech.com>
 * @author  Max Kalika <max@horde.org>
 * @author  Ben Chavet <ben@horde.org>
 * @author  Jan Schneider <jan@horde.org>
 * @package Sam
 */
class SAM_Driver_spamd_ftp extends SAM_Driver_spamd {

    /**
     * Constructs a new FTP storage object.
     *
     * @param string $user   The user who owns these SPAM options.
     * @param array $params  A hash containing connection parameters.
     */
    function SAM_Driver_spamd_ftp($user, $params = array())
    {
        $default_params = array(
            'hostspec'   => 'localhost',
            'port'       => 21,
            'user_prefs' => '.spamassassin/user_prefs'
        );
        $this->_user = $user;
        $this->_params = array_merge($default_params, $params);
        $this->_params['vfstype'] = 'ftp';
    }

    /**
     * Retrieves the user options and stores them in the member array.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function retrieve()
    {
        $options = $this->_retrieve();
        if (!is_a($options, 'PEAR_Error')) {
            $this->_options = $options;
        } else {
            return $options;
        }

        return true;
    }

    /**
     * Stores the user options from the member array.
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function store()
    {
        return $this->_store();
    }

    /**
     * Retrieve an option set from the storage backend.
     *
     * @access private
     *
     * @return mixed  Array of field-value pairs or a PEAR_Error object on
     *                failure.
     */
    function _retrieve()
    {
        $this->_params['username'] = $this->_user;
        $this->_params['password'] = Horde_Auth::getCredential('password');

        // Get config file(s).
        require_once 'VFS.php';
        $vfs = &VFS::singleton('ftp', $this->_params);
        $content = $vfs->read('', $this->_params['system_prefs']);
        if (is_a($content, 'PEAR_Error')) {
            return $content;
        }
        $conf = $this->_parse($content);
        $content = $vfs->read('', $this->_params['user_prefs']);
        if (is_a($content, 'PEAR_Error')) {
            return $content;
        }
        $conf = array_merge($conf, $this->_parse($content));

        $return = array();
        foreach ($conf as $option => $value) {
            $return[$this->_mapOptionToAttribute($option)] = $value;
        }
        return $return;
    }

    /**
     * Parses the file into an option-value-hash.
     *
     * @access private
     *
     * @param string $config  The configuration file contents.
     *
     * @return array  Hash with options and values.
     */
    function _parse($config)
    {
        $config = explode("\n", $config);
        $parsed = array();
        foreach ($config as $line) {
            // Ignore comments and whitespace.
            $line = trim(substr($line, 0, strcspn($line, '#')));
            if (!empty($line)) {
                $split = preg_split('/\s+/', $line, 2);
                if (count($split) == 2) {
                    $parsed[$split[0]] = $split[1];
                }
            }
        }

        return $parsed;
    }

    /**
     * Store an option set from the member array to the storage
     * backend.
     *
     * @access private
     *
     * @return mixed  True on success or a PEAR_Error object on failure.
     */
    function _store()
    {
        $this->_params['username'] = $this->_user;
        $this->_params['password'] = Horde_Auth::getCredential('password');

        // Generate config file.
        $output = _("# SpamAssassin config file generated by SAM") . ' (' . date('F j, Y, g:i a') . ")\n";
        $store = $this->_options;
        foreach ($store as $attribute => $value) {
            if (is_array($value)) {
                $output .= $this->_mapAttributeToOption($attribute) . ' ' . trim(implode(' ', $value)) . "\n";
            } else {
                $output .= $this->_mapAttributeToOption($attribute) . ' ' . trim($value) . "\n";
            }
        }

        // Write config file.
        require_once 'VFS.php';
        $vfs = &VFS::singleton('ftp', $this->_params);
        return $vfs->writeData(dirname($this->_params['user_prefs']), basename($this->_params['user_prefs']), $output, true);
    }

}
