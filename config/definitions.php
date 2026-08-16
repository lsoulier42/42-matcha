<?php

declare(strict_types=1);

/*
 * Définitions du conteneur php-di.
 * Les contrôleurs/services sont autowirés automatiquement par php-di :
 * seules les dépendances à configuration explicite sont déclarées ici.
 */

use App\Controllers\AuthController;
use App\Db\ConnectionFactory;
use App\Db\Query;
use App\Services\MailService;
use DI\Container;
use Slim\Views\Twig;
$settings = require __DIR__ . '/settings.php';

return [
    'settings' => $settings,

    PDO::class => static function (Container $c): PDO {
        return ConnectionFactory::create($c->get('settings')['db']);
    },

    Query::class => static function (Container $c): Query {
        return new Query($c->get(PDO::class));
    },

    Twig::class => static function (Container $c): Twig {
        $cfg = $c->get('settings')['twig'];
        return Twig::create($cfg['templates'], $cfg['options']);
    },

    // Services à configuration scalaire (tableaux) : injection explicite.
    MailService::class => static function (Container $c): MailService {
        return new MailService($c->get('settings')['mail']);
    },

    AuthController::class => static function (Container $c): AuthController {
        return new AuthController(
            $c->get(Twig::class),
            $c->get(Query::class),
            $c->get(MailService::class),
            $c->get('settings')['app']['url']
        );
    },
];
