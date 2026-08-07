<?php

namespace App;

enum DiscogsFailure: string
{
    case Authentication = 'authentication';
    case NotFound = 'not_found';
    case Transient = 'transient';
    case Unexpected = 'unexpected';
}
