<?php

namespace App\Enums;

class ActivityAction
{
    public const LOGIN             = 'User Logged In';
    public const LOGOUT            = 'User Logged Out';
    public const LOGIN_FAILED      = 'Login Failed';
    public const USER_CREATED      = 'User Created';
    public const USER_UPDATED      = 'User Updated';
    public const USER_DELETED      = 'User Deleted';
    public const USER_RESTORED     = 'User Restored';
    public const USER_ARCHIVED     = 'User Archived';
    public const USER_ACTIVATED    = 'User Activated';
    public const USER_DEACTIVATED  = 'User Deactivated';
    public const PASSWORD_RESET    = 'Password Reset';

    public const SPOT_ADDED        = 'Tourist Spot Added';
    public const SPOT_UPDATED      = 'Tourist Spot Updated';
    public const SPOT_DELETED      = 'Tourist Spot Deleted';
    public const SPOT_APPROVED     = 'Tourist Spot Approved';
    public const SPOT_REJECTED     = 'Tourist Spot Rejected';
    public const SPOT_IMAGE_UPLOAD = 'Tourist Spot Image Uploaded';

    public const FARE_UPLOADED     = 'Fare Data Uploaded';
    public const FARE_UPDATED      = 'Fare Data Updated';
    public const FARE_DELETED      = 'Fare Data Deleted';

    public const SETTINGS_UPDATED  = 'System Settings Updated';
    public const PROFILE_UPDATED   = 'Profile Updated';
    public const PASSWORD_CHANGED  = 'Password Changed';

    public const DATA_IMPORTED     = 'Data Imported';
    public const DATA_EXPORTED     = 'Data Exported';
}
