<?php
/**
 *  * Created by mtils on 21.12.2022 at 20:58.
 **/

namespace Koansu\Schema\Contracts;

use Koansu\Schema\MigrationStep;

interface MigrationStepRepository
{

    /**
     * Get all migrations ordered by sequence asc
     *
     * @return MigrationStep[]
     */
    public function all() : array;

    /**
     * Save the state of $step.
     *
     * @param MigrationStep $step
     *
     * @return bool
     */
    public function save(MigrationStep $step) : bool;

    /**
     * Install the migration repository.
     */
    public function install() : void;
}