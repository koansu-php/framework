<?php
/**
 *  * Created by mtils on 09.10.2022 at 07:21.
 **/

namespace Koansu\Core\Contracts;

use Koansu\Core\Url;

/**
 * This is the base interface for any connection. May it database, ftp, file,...
 * A Connection must *never* open a connection in __construct(), is has
 * to open when reading or writing on it or an explicit call to open().
 **/
interface Connection
{
    /**
     * Opens the connection. If something went wrong throw an exception.
     *
     * @return void
     **/
    public function open() : void;

    /**
     * Closes the connection.
     *
     * @return void
     **/
    public function close() : void;

    /**
     * Check if the connection is opened.
     *
     * @return bool
     **/
    public function isOpen() : bool;

    /**
     * Return the underlying resource. This could be a real resource or an object.
     *
     * @return resource|object|null
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function resource();

    /**
     * Return the url of this connection. You should represent any connection
     * target as an url, also database or local connections. The connection url
     * is generally immutable, so it has no get prefix.
     *
     * @return Url
     **/
    public function url() : Url;
}