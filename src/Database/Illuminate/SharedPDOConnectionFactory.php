<?php
/**
 *  * Created by mtils on 21.01.2023 at 09:30.
 **/

namespace Koansu\Database\Illuminate;

use Illuminate\Database\Connectors\ConnectionFactory;

class SharedPDOConnectionFactory extends ConnectionFactory
{
    protected function createPdoResolver(array $config)
    {
        if (isset($config['pdo']) && $config['pdo']) {
            return $config['pdo'];
        }
        return parent::createPdoResolver($config);
    }
}