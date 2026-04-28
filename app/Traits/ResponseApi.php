<?php

namespace App\Traits;
use Illuminate\Pagination\LengthAwarePaginator;

trait ResponseApi
{
    public function successApi($data = null, string $message = 'Operation successful' , int $statusCode = 200)
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data ,
        ] ;

        if($data instanceof LengthAwarePaginator)
            {
                $response['data'] = $data->items() ;
                $response['pagination'] = [
                    'total'=> $data->total(),
                    'per_page'=> $data->perPage(),
                    'current_page'=> $data->currentPage(),
                    'last_page'=> $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to'   => $data->lastItem(),
                    'links' => [
                        'first' => $data->url(1),
                        'last'  => $data->url($data->lastPage()),
                        'prev'  => $data->previousPageUrl(),
                        'next'  => $data->nextPageUrl(),
                    ],
                ] ;
            }
        return response()->json($response , $statusCode) ;
    }

    public function errorApi(string $message = 'Something went wrong', int $code = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message ,
        ];

        if($errors)
        {
            $response['errors'] = $errors ;
        }
        return response()->json($response , $code) ;
    }
}
