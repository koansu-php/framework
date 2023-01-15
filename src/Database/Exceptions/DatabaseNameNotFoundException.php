<?php
/**
 *  * Created by mtils on 24.12.2022 at 10:38.
 **/

namespace Koansu\Database\Exceptions;

/**
 * A SQLNameNotFoundException is used to mark an exception as caused by
 * a missing database, table or a column:
 **/
class DatabaseNameNotFoundException extends DatabaseException
{
    /**
     * Here you can mark if database|table|column|view was not found.
     *
     * @var string
     **/
    public $missingType = '';
}