<?php

declare(strict_types=1);

namespace WizcodePl\LunarIdosell\Enums;

enum IdosellLinkStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
