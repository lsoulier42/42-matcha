<?php

declare(strict_types=1);

/*
 * php-di container definitions.
 * Controllers/services are autowired automatically by php-di:
 * only dependencies needing explicit configuration are declared here.
 */

use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Db\ConnectionFactory;
use App\Db\Query;
use App\Repository\BlockRepository;
use App\Repository\LikeRepository;
use App\Repository\PhotoRepository;
use App\Repository\TagRepository;
use App\Repository\TokenRepository;
use App\Repository\UserRepository;
use App\Repository\VisitRepository;
use App\Security\AuthService;
use App\Services\MailService;
use App\Services\MatchingService;
use App\Services\PhotoService;
use App\Validation\LocationValidator;
use App\Validation\ProfileValidator;
use App\Validation\RegisterValidator;
use App\Validation\TagValidator;
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

    // Services with scalar configuration (arrays): explicit injection.
    MailService::class => static function (Container $c): MailService {
        return new MailService($c->get('settings')['mail']);
    },

    PhotoService::class => static function (Container $c): PhotoService {
        return new PhotoService($c->get(PhotoRepository::class), $c->get('settings'));
    },

    MatchingService::class => static function (Container $c): MatchingService {
        return new MatchingService(
            $c->get(UserRepository::class),
            $c->get(TagRepository::class),
            $c->get(BlockRepository::class),
            $c->get('settings')
        );
    },

    ProfileController::class => static function (Container $c): ProfileController {
        return new ProfileController(
            $c->get(Twig::class),
            $c->get(UserRepository::class),
            $c->get(TagRepository::class),
            $c->get(LikeRepository::class),
            $c->get(VisitRepository::class),
            $c->get(PhotoService::class),
            $c->get(App\Services\PopularityService::class),
            $c->get(ProfileValidator::class),
            $c->get(LocationValidator::class),
            $c->get(TagValidator::class),
            $c->get(App\Services\ReverseGeocoder::class)
        );
    },

    AuthService::class => static function (Container $c): AuthService {
        return new AuthService(
            $c->get(UserRepository::class),
            $c->get(TokenRepository::class),
            $c->get(MailService::class),
            $c->get('settings')['app']['url'],
        );
    },

    AuthController::class => static function (Container $c): AuthController {
        return new AuthController(
            $c->get(Twig::class),
            $c->get(UserRepository::class),
            $c->get(AuthService::class),
            $c->get(RegisterValidator::class),
        );
    },
];
