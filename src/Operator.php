<?php

namespace Tivins\Webapp;

enum Operator: string {
    case Equals = '=';
    case Greater = '>';
    case GreaterOrEquals = '>=';
    case Less = '<';
    case LessOrEquals = '<=';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    // Todo Implement operators 'in'
    // case In = 'IN';
    // case NotIn = 'NOT IN';
}