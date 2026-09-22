<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends Controller
{
    public function index(): string
    {
        return 'Hello World';
    }

    public function json(): ResponseInterface
    {
        return $this->response->setJSON(['time' => 0.0]);
    }
}
