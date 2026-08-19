<?php

namespace Modules\Garage\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Garage\Models\ServiceJob;
use Modules\Garage\Transformers\Dashboard\ServiceJobResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Back-office view of captured service jobs (read-only). Owners review the job,
 * its photo proof, and its settlement status — the anti-fraud / trust surface.
 * Jobs are created by the Garage capture app, never here.
 */
class ServiceJobController extends Controller
{
	public function __construct()
	{
		$this->authorizeResource(ServiceJob::class, parameter: 'service_job');
	}

	/**
	 * Display a listing of captured service jobs.
	 */
	public function index()
	{
		$jobs = QueryBuilder::for(ServiceJob::class)
			->with(['vehicle', 'customer', 'order'])
			->withCount(['proofMedia', 'lines'])
			->allowedFilters(...[
				AllowedFilter::exact('status'),
				AllowedFilter::callback('search', function ($query, $value) {
					$query->where(function ($q) use ($value) {
						$q->where('technician', 'like', "%{$value}%")
							->orWhere('membership_ref', 'like', "%{$value}%")
							->orWhereHas('vehicle', function ($v) use ($value) {
								$v->where('plate_number', 'like', "%{$value}%")
									->orWhere('make', 'like', "%{$value}%")
									->orWhere('model', 'like', "%{$value}%");
							});
					});
				}),
			])
			->latest('submitted_at')
			->paginate($this->limit());

		$items = ServiceJobResource::collection($jobs)->response()->getData(true);

		return Inertia::render('Garage::Dashboard/ServiceJobs/Index', [
			'items' => $items,
		]);
	}

	/**
	 * Display a captured service job with its lines, photo proof, and ticket.
	 */
	public function show(ServiceJob $serviceJob)
	{
		$serviceJob->load(['vehicle', 'customer', 'order', 'lines', 'proofMedia'])
			->loadCount(['proofMedia', 'lines']);

		return Inertia::render('Garage::Dashboard/ServiceJobs/Show', [
			'item' => new ServiceJobResource($serviceJob),
		]);
	}
}
