<?php

namespace App\Controllers;

use App\Models\{
    Driver,
    Result,
    Session,
    Standing,
    Event
};
use App\Controllers\Controller;
use League\Fractal\{
    Resource\Item,
    Resource\Collection
};
use App\Transformers\{
    DriverTransformer,
    ResultTransformer,
    TeamTransformer,
    SessionTransformer,
    EventTransformer
};

/**
 * DriverController
 */
class DriverController extends Controller
{
    public function index($requst, $response, $api = false) {
        $drivers = Driver::all();

        $transformer = new Collection($drivers, new DriverTransformer);

        if ($api) {
            return $response->withJson($this->c->fractal->createData($transformer)->toArray()["data"]);
        }

        return $response->withJson($this->c->fractal->createData($transformer)->toArray());
    }

    public function apiIndex($request, $response) {
        return $this->index($request, $response, $api = true);
    }

    public function show($requst, $response, $args) {
        $driver = Driver::where('short_name', $args['name'])->first();
        $events = Event::fromDriver($driver->id)->orderBy('planned_start', 'DESC')->get();

        if ($driver === null) {
            return $response->withStatus(500);
        }

        $results = Result::fromDriver($driver->id)->isRace()->isOfficial()->get()->sortByDesc(function($query){
               return $query->session->start;
            });

        $driverTransformer = new Item($driver, new DriverTransformer);
        $eventTransformer = new Collection($events, new EventTransformer);

        if (!$results->isEmpty()) {
            $resultTransformer = new Collection($results, new ResultTransformer);
            $teamTransformer = new Item($driver->team, new TeamTransformer);
            $data = [
                "driver" => $this->c->fractal->createData($driverTransformer)->toArray()["data"],
                "team" => $this->c->fractal->createData($teamTransformer)->toArray()["data"],
                "results" => $this->c->fractal->createData($resultTransformer)->toArray()["data"],
                "events" => $this->c->fractal->createData($eventTransformer)->toArray()["data"]
            ];
        } else {
            $data = [
                "driver" => $this->c->fractal->createData($driverTransformer)->toArray()["data"]
            ];
        }

        return $this->c->view->render($response, 'drivers/show.twig', $data);
    }

    public function add($request, $response) {
        $driver = new Driver;

        $driver->name = $request->getParam("name");
        $driver->team_id = $request->getParam("team_id");
        $driver->short_name = $request->getParam("short_name");

        $driver->save();

        return $response->withJson($driver);
    }

    public function update($request, $response, $args) {
        $driver = Driver::where('name', $args['name'])->first();

        foreach (array_keys($request->getParams()) as $param) {
            $driver[$param] = $request->getParam($param);
        }

        if ($driver->isDirty()) {
            $driver->save();
        }

        return $response->withJson($driver);
    }

    public function destroy($request, $response, $args) {
        $driver = Driver::where('name', $args['name'])->first();

        if ($driver === null) {
            return $response->withStatus(500);
        } else {
            $driver->delete();
            return $response->withStatus(204);
        }   
    }

    public function calcPoints($position, $point_system, $fastest_lap) {
        $points = [
            [25, 18, 15, 12, 10, 8, 6, 4, 2, 1],
            [10, 6, 4, 3, 2, 1]
        ];

        $points = $points[$point_system - 1];

        if ($position < sizeof($points) + 1) {
            return $points[$position - 1] + (int)$fastest_lap;
        }
        else {
            return 0;
        }
    }
}
