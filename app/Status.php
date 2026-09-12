<?php

namespace App;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Transferred = 'transferred';
}
