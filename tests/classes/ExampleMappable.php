<?php

namespace Tivins\WebappTests\classes;

use DateTime;
use Tivins\Webapp\Mappable;

class ExampleMappable extends Mappable
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public DateTime $date = new DateTime(),
    ) {
    }
}