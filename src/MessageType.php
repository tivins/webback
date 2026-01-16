<?php

namespace Tivins\Webapp;

enum MessageType: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';
}
