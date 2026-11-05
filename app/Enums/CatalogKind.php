<?php

namespace App\Enums;

enum CatalogKind: string
{
    case Package = 'PACKAGE';
    case Addon = 'ADDON';
}
