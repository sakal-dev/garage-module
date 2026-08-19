<?php

namespace Modules\Garage\Models;

use App\Traits\HasUuidTrait;
use App\Traits\Tenancy\BelongsToMorphTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Customer\Models\Customer;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

/**
 * A customer-owned vehicle serviced by a Garage tenant (car care / wash / repair).
 *
 * Dependency direction: Garage → Customer. This model references the core Customer
 * module; Customer never references Garage, keeping the core reusable/standalone.
 *
 * Tenancy is SELF-OWNED (BelongsToMorphTenant), not inherited from the customer —
 * so a vehicle can exist with a null customer (plate-first / future ANPR capture)
 * and still be correctly scoped to its shop.
 */
class Vehicle extends Model implements Searchable
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
	protected $table = 'garage_vehicles';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'customer_id',
		'vehicle_size_id',
		'client_uuid',
		'plate_number',
		'make',
		'model',
		'vin',
		'size_code',
		'color',
		'year',
		'notes',
		'is_default',
		'active',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array
	 */
	protected $casts = [
		'year' => 'integer',
		'is_default' => 'boolean',
		'active' => 'boolean',
	];

	/**
	 * The customer who owns this vehicle (nullable: walk-in / plate-first capture).
	 */
	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class);
	}

	/**
	 * The configurable size class used for pricing lookups.
	 */
	public function size(): BelongsTo
	{
		return $this->belongsTo(VehicleSize::class, 'vehicle_size_id');
	}

	/**
	 * The service jobs captured for this vehicle (its service history).
	 */
	public function serviceJobs(): HasMany
	{
		return $this->hasMany(ServiceJob::class);
	}

	/**
	 * The effective size code: the resolved taxonomy code, else the captured
	 * raw code the tech picked at check-in.
	 */
	public function getEffectiveSizeCodeAttribute(): ?string
	{
		return $this->size?->code ?? $this->size_code;
	}

	/**
	 * Human-readable label, e.g. "Toyota Camry (2ABC-1234)".
	 */
	public function getLabelAttribute(): string
	{
		$name = trim(($this->make ?? '').' '.($this->model ?? ''));
		$name = $name !== '' ? $name : __('Vehicle');

		return $this->plate_number ? $name.' ('.$this->plate_number.')' : $name;
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
	 * The searchable type display (plate lookup at the till).
	 *
	 * @var string
	 */
	public $searchableType = 'Vehicle';

	/**
	 * Build the search result for the model.
	 *
	 * @return \Spatie\Searchable\SearchResult
	 */
	public function getSearchResult(): SearchResult
	{
		// Garage dashboard routes are not defined yet; use a placeholder url until
		// the show page exists so global search never resolves a missing route.
		$url = '#';

		return new SearchResult($this, $this->label, $url);
	}

	/**
	 * Keep a single default vehicle per customer, mirroring Customer\Address.
	 *
	 * @return void
	 */
	protected static function boot()
	{
		parent::boot();

		static::creating(function ($model) {
			if (! $model->customer_id) {
				return;
			}

			if ($model->newQuery()->where('customer_id', $model->customer_id)->count() === 0) {
				$model->is_default = true;
			}

			if ($model->is_default) {
				$model->newQuery()->where('customer_id', $model->customer_id)->update(['is_default' => false]);
			}
		});

		static::updating(function ($model) {
			if ($model->customer_id && $model->is_default && $model->isDirty('is_default')) {
				$model->newQuery()
					->where('customer_id', $model->customer_id)
					->where('id', '!=', $model->id)
					->update(['is_default' => false]);
			}
		});
	}

	/**
	 * Scope to the default vehicle.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 */
	public function scopeDefault($query)
	{
		return $query->where('is_default', true);
	}

	/**
	 * Scope to active (non-archived) vehicles.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 */
	public function scopeActive($query)
	{
		return $query->where('active', true);
	}

	/**
	 * Get the factory for the model.
	 *
	 * @return \Illuminate\Database\Eloquent\Factories\Factory
	 */
	protected static function newFactory()
	{
		return \Modules\Garage\Database\Factories\VehicleFactory::new();
	}
}
