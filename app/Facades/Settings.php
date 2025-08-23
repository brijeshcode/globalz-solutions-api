<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $group, string $key, $default = null)
 * @method static \App\Models\Setting set(string $group, string $key, $value, string $dataType = 'string', string $description = null)
 * @method static mixed userGet(int $userId = null, string $key, $default = null, string $globalGroup = 'system')
 * @method static \App\Models\UserSetting userSet(int $userId = null, string $key, $value, string $dataType = 'string', string $description = null)
 * @method static mixed config(string $configKey, string $settingsGroup, string $settingsKey, $default = null)
 * @method static string appName()
 * @method static string timezone()
 * @method static int globalPagination()
 * @method static int itemsPagination()
 * @method static int suppliersPagination()
 * @method static string currencySymbol()
 * @method static string dateFormat()
 * @method static int decimalPlaces()
 * @method static string userTheme(int $userId = null)
 * @method static string userLanguage(int $userId = null)
 * @method static string userTimezone(int $userId = null)
 * @method static string userLayout(int $userId = null)
 * @method static bool userNotificationsEnabled(int $userId = null)
 * @method static string getNextItemCode()
 * @method static int incrementItemCode()
 * @method static string getNextSupplierCode()
 * @method static int incrementSupplierCode()
 * @method static array getGroup(string $group)
 * @method static array getAllUserSettings(int $userId = null)
 * @method static bool isValidDataType(string $type)
 * @method static array getDataTypes()
 * @method static void clearCache()
 * @method static bool isFeatureEnabled(string $feature)
 * @method static \App\Models\Setting enableFeature(string $feature)
 * @method static \App\Models\Setting disableFeature(string $feature)
 * @method static int getPaginationSize(string $context = 'global', int $userId = null)
 *
 * @see \App\Helpers\SettingsHelper
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'settings';
    }
}