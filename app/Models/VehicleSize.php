<?php

namespace Modules\Garage\Models;

use App\Traits\HasUuidTrait;
use App\Traits\Tenancy\BelongsToMorphTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * A per-tenant, configurable vehicle size class (e.g. Small / Sedan / SUV / Truck).
 *
 * The Garage module owns the size *taxonomy*; the price attached to a size for
 * membership/wash pricing is resolved by the Membership module, not here — this
 * table only classifies the vehicle so pricing stays configurable per tenant.
 */
class VehicleSize extends Model
{
	use BelongsToMorphTenant;
	use HasFactory;
	use HasUuidTrait;
	use LogsActivity;
	use SoftDeletes;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'garage_vehicle_sizes';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name',
		'code',
		'sort_order',
		'active',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array
	 */
	protected $casts = [
		'sort_order' => 'integer',
		'active' => 'boolean',
	];

	/**
	 * The vehicles classified with this size.
	 */
	public function vehicles(): HasMany
	{
		return $this->hasMany(Vehicle::class);
	}

	/**
	 * The attributes that should be logged as activity.
	 */
	public function getActivitylogOptions(): LogOptions
	{
		return LogOptions::defaults()
			->logFillable()
			->logOnlyDirty()
			->dontLogEmptyChanges();
	}

	/**
	 * Get the factory for the model.
	 *
	 * @return \Illuminate\Database\Eloquent\Factories\Factory
	 */
	protected static function newFactory()
	{
		return \Modules\Garage\Database\Factories\VehicleSizeFactory::new();
	}
}
