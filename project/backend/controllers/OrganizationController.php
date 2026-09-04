<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Organization.php';

class OrganizationController
{
    public static function index(): void
    {
        AuthMiddleware::user();
        Response::success(['items' => Organization::all()]);
    }
}
