<?php

namespace Modules\Garage\Models;

use App\Traits\HasMediaTrait;
use App\Traits\HasUuidTrait;
use App\Traits\Tenancy\BelongsToMorphTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Models\Customer;
use Modules\Garage\Enums\ServiceJobStatusEnum;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * A captured vehicle-service job (GR-03/GR-05): services + parts performed on a
 * vehicle, submitted by the bay technician. Authoritative capture record; it
 * never takes payment and hands off to the front-desk POS to settle.
 *
 * Idempotency: `job_uuid` (the capture app's key) is unique per tenant, so a
 * retried submit resolves to one job.
 */
class ServiceJob extends Model
{
	use BelongsToMorphTenant;
	use HasFactory;
	use HasMediaTrait;
	use HasUuidTrait;
	use LogsActivity;
	use SoftDeletes;

	protected $table = 'garage_service_jobs';

	protected $fillable = [
		'job_uuid',
		'vehicle_id',
		'customer_id',
		'order_id',
		'location_id',
		'membership_ref',
		'odometer',
		'technician',
		'note',
		'signature_ref',
		'status',
		'submitted_at',
		'settled_at',
	];

	protected function casts(): array
	{
		return [
			'odometer' => 'integer',
			'status' => ServiceJobStatusEnum::class,
			'submitted_at' => 'datetime',
			'settled_at' => 'datetime',
		];
	}

	/**
	 * The vehicle this job was performed on (nullable: plate-first capture).
	 */
	public function vehicle(): BelongsTo
	{
		return $this->belongsTo(Vehicle::class);
	}

	/**
	 * The customer billed at settlement (nullable: walk-in).
	 */
	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class);
	}

	/**
	 * The POS Order this job was projected into for settlement (GR-05). Null
	 * until submitted. The order is a DRAFT (open, unpaid ticket) until the desk
	 * settles it, so this relation opts out of the Order module's global
	 * ExcludeDraftOrdersScope — otherwise the open ticket would be invisible here.
	 */
	public function order(): BelongsTo
	{
		return $this->belongsTo(\Modules\Order\Models\Order::class)
			->withoutGlobalScope(\Modules\Order\Scopes\ExcludeDraftOrdersScope::class);
	}

	/**
	 * The service + part lines on this job.
	 */
	public function lines(): HasMany
	{
		return $this->hasMany(ServiceJobLine::class);
	}

	/**
	 * The photo-proof attachments (plate/before/after/condition/signature).
	 */
	public function proofMedia(): HasMany
	{
		return $this->hasMany(ServiceJobMedia::class);
	}

	/**
	 * Display total of the lines (informational only — the POS settles money).
	 */
	public function getDisplayTotalAttribute(): float
	{
		return (float) $this->lines->sum(
			fn (ServiceJobLine $l) => $l->membership_redemption ? 0 : (float) ($l->unit_price ?? 0) * $l->qty
		);
	}

	public function getActivitylogOptions(): LogOptions
	{
		return LogOptions::defaults()
			->logFillable()
			->logOnlyDirty()
			->dontLogEmptyChanges();
	}

	protected static function newFactory()
	{
		return \Modules\Garage\Database\Factories\ServiceJobFactory::new();
	}
}
