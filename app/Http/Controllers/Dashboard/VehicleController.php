<?php

namespace Modules\Garage\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Garage\Actions\CreateVehicleAction;
use Modules\Garage\Actions\DeleteVehicleAction;
use Modules\Garage\Actions\UpdateVehicleAction;
use Modules\Garage\Http\Requests\Dashboard\StoreVehicleRequest;
use Modules\Garage\Http\Requests\Dashboard\UpdateVehicleRequest;
use Modules\Garage\Models\Vehicle;
use Modules\Garage\Transformers\Dashboard\VehicleResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class VehicleController extends Controller
{
    /**
     * Bind resource authorization policies to this controller.
     */
    public function __construct()
    {
        $this->authorizeResource(Vehicle::class, parameter: 'Vehicle');
    }

    /**
     * Display a listing of vehicles.
     */
    public function index()
    {
        $vehicles = QueryBuilder::for(Vehicle::class)
            ->with(['customer', 'size'])
            ->allowedFilters(...[
                'plate_number',
                'make',
                'model',
                AllowedFilter::exact('customer_id'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('plate_number', 'like', "%{$value}%")
                            ->orWhere('make', 'like', "%{$value}%")
                            ->orWhere('model', 'like', "%{$value}%")
                            ->orWhere('color', 'like', "%{$value}%");
                    });
                }),
            ])
            ->latest()
            ->paginate($this->limit());

        $items = VehicleResource::collection($vehicles)->response()->getData(true);

        return Inertia::render('Garage::Dashboard/Vehicles/Index', [
            'items' => $items,
        ]);
    }

    /**
     * Show the modal for creating a new vehicle.
     */
    public function create()
    {
        return Inertia::modal('Garage::Dashboard/Vehicles/Create')
            ->baseRoute('dashboard.garage.vehicles.index');
    }

    /**
     * Store a newly created vehicle.
     */
    public function store(StoreVehicleRequest $request)
    {
        DB::beginTransaction();

        try {
            CreateVehicleAction::run($request->validated());

            DB::commit();

            return redirect()->toIndex('dashboard.garage.vehicles.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    /**
     * Show the modal for editing a vehicle.
     */
    public function edit(Vehicle $vehicle)
    {
        return Inertia::modal('Garage::Dashboard/Vehicles/Edit', [
            'vehicle' => new VehicleResource($vehicle->load(['customer', 'size'])),
        ])->baseRoute('dashboard.garage.vehicles.index');
    }

    /**
     * Update the given vehicle.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        DB::beginTransaction();

        try {
            UpdateVehicleAction::run($vehicle, $request->validated());

            DB::commit();

            return redirect()->toIndex('dashboard.garage.vehicles.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withError($e->getMessage())->withInput();
        }
    }

    /**
     * Show the delete confirmation modal.
     */
    public function delete(Vehicle $vehicle)
    {
        return Inertia::modal('Garage::Dashboard/Vehicles/Delete', [
            'vehicle' => new VehicleResource($vehicle->load('customer')),
        ])->baseRoute('dashboard.garage.vehicles.index');
    }

    /**
     * Remove the given vehicle.
     */
    public function destroy(Vehicle $vehicle)
    {
        DB::beginTransaction();

        try {
            DeleteVehicleAction::run($vehicle);

            DB::commit();

            return redirect()->toIndex('dashboard.garage.vehicles.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withError($e->getMessage());
        }
    }
}
