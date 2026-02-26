<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $events = Event::query()
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                    $q->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->paginate(10)
            ->withQueryString();


        // return $events;

        return Inertia::render('Admin/Event/Index', [
            'events' => $events,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
        return Inertia::render('Admin/Event/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = (object) $request->validate([
            'name' => 'required|string|max:255|unique:events,name',
            'address' => 'required|string',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
            'description' => 'nullable|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $event = new Event();
        foreach($data as $col => $val)
            $event->$col = $val;

        $event->save();

        Inertia::flash([
            'header' => "Create success",
            'message' => "You have successfully created event $event->name"
        ]);

        return to_route('admin.event.edit',$event->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        return Inertia::render('Admin/Event/Edit', [
            'event' => $event
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $data = (object) $request->validate([
            'name' => 'required|string|max:255|unique:events,name,'.$event->id,
            'address' => 'required|string',
            'event_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
            'description' => 'nullable|string',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);
        foreach($data as $col => $val){
            $event->$col = $val;
        }

        $event->save();

        Inertia::flash([
            'header' => "Update success",
            'message' => "You have successfully updated event $event->name"
        ]);

        return to_route('admin.event.edit',$event->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $event->delete();
        Inertia::flash([
            'header' => "Delete success",
            'message' => "You have successfully removed an event"
        ]);

        return to_route('admin.event.index');
    }
}
