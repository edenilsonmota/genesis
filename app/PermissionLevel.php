<?php

namespace App;

enum PermissionLevel: string
{
    case Read = 'read';
    case Write = 'write';
}
