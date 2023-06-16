<?php

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $builder) {
    $builder->setRouteClass(DashedRoute::class);
    $builder->plugin('Burzum/FileStorage', function(RouteBuilder $builder) {
        $builder->fallbacks();
    });
};
