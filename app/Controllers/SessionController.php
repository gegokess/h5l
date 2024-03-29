<?php

namespace App\Controllers;

use App\Models\{
    Session,
    Result,
    Driver,
    Laptime,
    SafetyCarPhase
};
use App\Transformers\{
    SessionTransformer,
    ResultTransformer,
    LaptimeTransformer,
    PenaltyTransformer
};
use App\Controllers\Controller;
use League\Fractal\{
    Resource\Item,
    Resource\Collection
};

/**
 * SessionController
 */
class SessionController extends Controller
{
    public function show($request, $response, $args) {

        $session = Session::find($args["id"]);
        $drivers = Driver::fromSession($session->id)->orderBy('team_id', 'ASC')->get();
        $scPhases = SafetyCarPhase::fromSession($session->id)->get();

        if ($session) {
            $type = $session->type;
            $results = $session->results;
            $grid = $session->grid;
            $laptimes = $session->laptimes;
            $penalties = $session->penalties->sortBy('lap');

            $laptimesWithoutBox = Laptime::fromSession($session->id)->orderBy('time', 'ASC')->get();

            $groups = $laptimesWithoutBox->split(2);
            $medianLaptime = $groups[1]->first();
            $medianLaptime->time = $medianLaptime->time * 1.08;

            $chartData = array();
            $min = $laptimesWithoutBox->first();
            $max = $laptimesWithoutBox->last();

            foreach ($drivers as $driver) {
                $label = $driver->name;
                $data = array();
                $visible = "legendonly";

                foreach ($laptimes as $laptime) {
                    if ($driver->id == $laptime->driver_id) {
                        array_push($data, floatval($laptime->time));
                        if ($min == $laptime or $laptimes[6] == $laptime) {
                            $visible = true;
                        }
                    }
                }
                $array = array(
                    'label' => $driver->name,
                    'fill' => false,
                    'data' => $data,
                    'visible' => $visible,
                    'borderColor' => $driver->team->color
                );

                array_push($chartData, $array);
            }

            $min->time = $min->time * 0.99;
            $chartInfo = array(
                'min' => $min->timeAsString,
                'max' => $medianLaptime->timeAsString,
                'laps' => $session->laps
            );

            $sessionTransformer = new Item($session, new SessionTransformer);
            $resultTransformer = new Collection($results, new ResultTransformer);
            $laptimeTransformer = new Collection($laptimes, new LaptimeTransformer);
            $penaltyTransformer = new Collection($penalties, new PenaltyTransformer);
            
            $session = $this->c->fractal->createData($sessionTransformer)->toArray()["data"];
            $results = $this->c->fractal->createData($resultTransformer)->toArray()["data"];
            $laptimes = $this->c->fractal->createData($laptimeTransformer)->toArray()["data"];
            $penalties = $this->c->fractal->createData($penaltyTransformer)->toArray()["data"];


            if ($type == 10 or $type == 11) {
                $template = "race";
            } elseif ($type == 9) {
                $template = "OSQ";
            } elseif ($type == 8) {
                $template = "short_quali";
            }
            return $this->c->view->render($response, 'sessions/' . $template . '.twig', compact("session", "results", "grid", "laptimes", "penalties", "chartData", "chartInfo", "scPhases"));
        } else {
            return $response->withRedirect($this->c->router->pathFor('events.index'));
        }
    }
}
