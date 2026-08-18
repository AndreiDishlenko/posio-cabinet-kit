<?php

namespace Posio\CabinetKit\Traits;

use Spatie\Permission\Traits\HasRoles;

trait IsCabinetKitUser
{
    use HasRoles;
    use HasAccount;
    use HasCustomFields;
    use HasSettings;
}
