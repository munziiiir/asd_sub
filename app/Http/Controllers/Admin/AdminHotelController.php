<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Timezone;
use App\Http\Controllers\Admin\Concerns\HandlesRowHighlighting;
use App\Http\Controllers\Admin\Concerns\HandlesSearch;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminHotelController extends Controller
{
    use HandlesRowHighlighting;
    use HandlesSearch;

    private int $perPage = 10;

    public function index(Request $request): View
    {
        $search = (string) $request->string('search');

        $query = Hotel::query()
            ->select('hotels.*')
            ->with(['country', 'timezone'])
            ->leftJoin('countries', 'countries.code', '=', 'hotels.country_code')
            ->leftJoin('timezones', 'timezones.id', '=', 'hotels.timezone_id');

        if ($search) {
            $this->applyFuzzySearch($query, $search, [
                'hotels.name',
                'hotels.code',
                'countries.name',
                'countries.code',
                'timezones.timezone',
            ]);
        }

        $hotels = $query
            ->orderBy('hotels.name')
            ->orderBy('hotels.id')
            ->paginate($this->perPage)
            ->withQueryString();

        return view('admin.hotels.index', [
            'hotels' => $hotels,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.hotels.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $hotel = Hotel::create($data);
        $page = $this->pageForHotel($hotel);

        return $this->redirectWithHighlight(
            'admin.hotels.index',
            ['page' => $page],
            $hotel,
            'created',
            "Hotel {$hotel->name} created."
        );
    }

    public function edit(Hotel $hotel): View
    {
        $countryCode = $hotel->country?->code;

        return view('admin.hotels.edit', compact('hotel', 'countryCode'));
    }

    public function update(Request $request, Hotel $hotel): RedirectResponse
    {
        $data = $this->validated($request, $hotel->id);

        $hotel->update($data);
        $page = $this->pageForHotel($hotel);

        return $this->redirectWithHighlight(
            'admin.hotels.index',
            ['page' => $page],
            $hotel,
            'updated',
            'Hotel updated.'
        );
    }

    public function destroy(Hotel $hotel): RedirectResponse
    {
        try {
            $hotel->delete();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return back()->withErrors([
                    'hotel' => 'Cannot delete because related records exist',
                ]);
            }

            throw $e;
        }

        return redirect()
            ->route('admin.hotels.index')
            ->with('status', 'Hotel removed.');
    }

    private function validated(Request $request, ?int $hotelId = null): array
    {
        $codeRule = Rule::unique('hotels', 'code');

        if ($hotelId !== null) {
            $codeRule->ignore($hotelId);
        }

        $countryCode = $request->input('country_code');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', $codeRule],
            'country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'timezone_id' => [
                'required',
                'integer',
                Rule::exists('timezones', 'id')->where(fn ($query) => $query->where('country_code', $countryCode)),
            ],
        ]);

        $country = Country::where('code', $validated['country_code'])->first();

        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'country_code' => $country?->code,
            'timezone_id' => $validated['timezone_id'],
        ];
    }

    private function pageForHotel(Hotel $hotel): int
    {
        $beforeCount = Hotel::where('name', '<', $hotel->name)->count();
        $tieBreakerCount = Hotel::where('name', $hotel->name)
            ->where('id', '<', $hotel->id)
            ->count();

        $position = $beforeCount + $tieBreakerCount;

        return (int) floor($position / $this->perPage) + 1;
    }
}
