<?php
/**
 * This package is capable of interfacing with the open source Asterisk PBX via 
 * its built in Manager API.  This will allow you to execute manager commands
 * for administration and maintenance of the server.
 * 
 * Copyright (c) 2008-2014, Doug Bromley <doug@tintophat.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * - Redistributions of source code must retain the above copyright notice, 
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice, 
 *   this list of conditions and the following disclaimer in the documentation 
 *   and/or other materials provided with the distribution.
 * - Neither the name of the author nor the names of its 
 *   contributors may be used to endorse or promote products derived from 
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, 
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR 
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR 
 * CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, 
 * EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 
 * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE 
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, 
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * PHP version 5
 *
 * @category  Net
 * @package   Net_AsteriskManager
 * @author    Doug Bromley <doug@tintophat.com>
 * @copyright 2008-2014 Doug Bromley
 * @license   http://www.debian.org/misc/bsd.license New BSD License
 * @link      http://pear.php.net/pepr/pepr-proposal-show.php?id=543
 * @link      https://github.com/OdinsHat/asterisk-php-manager
 */

/**
 * PEAR Exception class is used for exception handling
 */
require_once 'PEAR/Exception.php';

/**
 * The exception class for the Asterisk Manager library.  Extends PEAR exception.
 * This class contains static values of error types.
 * 
 * @category Net
 * @package  Net_AsteriskManager
 * @author   Doug Bromley <doug.bromley@gmail.com>
 * @license  http://www.debian.org/misc/bsd.license New BSD License
 * @link     http://pear.php.net/pepr/pepr-proposal-show.php?id=543
 */
class Net_AsteriskManagerException extends PEAR_Exception
{
    const NOSOCKET      = 'No socket defined';
    const AUTHFAIL      = 'Authorisation failed';
    const CONNECTFAILED = 'Connection failed';
    const NOCOMMAND     = 'Unknown command specified';
    const NOPONG        = 'No response to ping';
    const NOSERVER      = 'No server specified';
    const MONITORFAIL   = 'Monitoring of channel failed';
    const RESPERR       = 'Server didn\'t respond as expected';
    const CMDSENDERR    = 'Sending of command failed';
}
