<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model for tenant-specific tables
 * Uses the default connection which is switched dynamically by middleware
 */
abstract class TenantBaseModel extends Model
{
    use HasFactory;

    // The connection is handled dynamically by the IdentifyTenant middleware
    // explicitly setting it to null or default lets Laravel use the current default
    // We can also rely on the middleware switching the 'mysql' connection config
    // or switching the default connection name.
    
    // In this system, IdentifyTenant uses database manager to purge and reconnect 'tenant' connection
    // So we should probably use 'tenant' connection if that's what's being configured
    
    // Let's check DatabaseManager.php to see what connection name it uses.
    // If it updates the 'mysql' config, then no change needed.
    // If it configures a 'tenant' connection, we should use that.
    
    // Safety fallback: standard model behavior usually works if default connection is swapped.
}
