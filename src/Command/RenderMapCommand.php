<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Map\MapRenderer;
use App\Service\Map\MapRequest;
use App\Service\Strava\AthleteProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:strava:map', description: 'Render a PNG with all matching activity traces overlaid (with or without OSM tiles).')]
final class RenderMapCommand extends Command
{
    public function __construct(
        private readonly AthleteProvider $athleteProvider,
        private readonly MapRenderer $renderer,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('center', null, InputOption::VALUE_REQUIRED, 'Center as "lat,lng"', '48.8566,2.3522')
            ->addOption('radius', null, InputOption::VALUE_REQUIRED, 'Radius in km', '20')
            ->addOption('background', null, InputOption::VALUE_REQUIRED, 'tiles | blank', MapRequest::BACKGROUND_TILES)
            ->addOption('bg-color', null, InputOption::VALUE_REQUIRED, 'Background colour (#hex) when --background=blank', '#000000')
            ->addOption('color', null, InputOption::VALUE_REQUIRED, 'Trace colour (#hex)', '#fc4c02')
            ->addOption('opacity', null, InputOption::VALUE_REQUIRED, 'Trace opacity [0-1]', '0.7')
            ->addOption('width', null, InputOption::VALUE_REQUIRED, 'Trace width in pixels', '2')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Image size as "WxH"', '2048x2048')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date YYYY-MM-DD', null)
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date YYYY-MM-DD', null)
            ->addOption('sport', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Sport types to include', [])
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output file path', 'map.png');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $athlete = $this->athleteProvider->find();
        if (null === $athlete) {
            $io->error('No athlete in DB yet — run app:strava:sync first.');

            return Command::FAILURE;
        }

        [$lat, $lng] = array_map('floatval', explode(',', (string) $input->getOption('center')));
        [$w, $h] = array_map('intval', explode('x', strtolower((string) $input->getOption('size'))));

        $req = new MapRequest(
            centerLat: $lat,
            centerLng: $lng,
            radiusMeters: ((float) $input->getOption('radius')) * 1000,
            background: 'blank' === $input->getOption('background') ? MapRequest::BACKGROUND_BLANK : MapRequest::BACKGROUND_TILES,
            backgroundColor: (string) $input->getOption('bg-color'),
            traceColor: (string) $input->getOption('color'),
            traceOpacity: (float) $input->getOption('opacity'),
            traceWidth: (int) $input->getOption('width'),
            width: $w,
            height: $h,
            from: $input->getOption('from') ? new \DateTimeImmutable((string) $input->getOption('from')) : null,
            to: $input->getOption('to') ? new \DateTimeImmutable($input->getOption('to').' 23:59:59') : null,
            sportTypes: array_values(array_map('strval', (array) $input->getOption('sport'))),
        );

        $io->writeln(sprintf(
            'Rendering map: center=(%.5f, %.5f), radius=%d m, background=%s, output=%s',
            $req->centerLat,
            $req->centerLng,
            (int) $req->radiusMeters,
            $req->background,
            (string) $input->getOption('out'),
        ));

        $result = $this->renderer->render($athlete, $req);

        $out = (string) $input->getOption('out');
        $this->filesystem->copy($result['path'], $out, true);

        $io->success(sprintf('Wrote %s — %s activities, zoom=%d.', $out, -1 === $result['count'] ? 'cached' : (string) $result['count'], $result['zoom']));

        return Command::SUCCESS;
    }
}
