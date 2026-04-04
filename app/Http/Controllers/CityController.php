<?php

namespace App\Http\Controllers;

use App\Repository\CityRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CityController extends Controller
{
    public function __construct(
        protected CityRepository $cityRepository
    ){
    }

    public function list(Request $request): JsonResponse
    {
        $stateId = $request->get('stateId');
        
        $cities = $this->cityRepository->getCityByState($stateId);

        return response()->json(['cities' => $cities], Response::HTTP_OK);
    }
}
