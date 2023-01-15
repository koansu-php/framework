<?php
/**
 *  * Created by mtils on 25.10.2022 at 16:28.
 **/

namespace Koansu\Routing\Contracts;

use Koansu\Core\Contracts\Storage;

interface Session extends Storage
{
    /**
     * Get the session ID
     *
     * @return string
     */
    public function getId() : string;

    /**
     * Set the id that is used by the handler to load the data.
     *
     * @param string $id
     * @return Session
     */
    public function setId(string $id) : Session;

    /**
     * Start the session. This should not be needed to be called manually.
     * It should start when somebody tries to access data of the session.
     *
     * @return bool
     */
    public function start() : bool;

    /**
     * Return true if the session was started.
     *
     * @return bool
     */
    public function isStarted() : bool;

}