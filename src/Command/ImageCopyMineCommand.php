<?php

namespace RokkaCli\Command;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImageCopyMineCommand extends ImageCopyCommand
{
    protected function configure()
    {
        $this
            ->setName('image:copy-mine')
            ->setDescription('copy all the available Images from the source organization')
            ->addArgument('dest-organization', InputArgument::REQUIRED, 'The destination organization to copy images to')
            ->addOption('source-organization', null, InputOption::VALUE_REQUIRED, 'The source organization to copy images from', null)
        ;
    }

    /**
     * @throws ClientException
     * @throws GuzzleException
     * @throws \RuntimeException
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orgSource = $input->getOption('source-organization');
        if (!$orgSource = $this->resolveOrganizationName($orgSource, $output)) {
            return -1;
        }

        $orgDest = $input->getArgument('dest-organization');
        if (!$orgDest = $this->resolveOrganizationName($orgDest, $output)) {
            return -1;
        }

        if ($orgSource === $orgDest) {
            $output->writeln($this->formatterHelper->formatBlock([
                'Error!',
                'The organizations to copy images between must not be the same: "'.$orgSource.'"!',
            ], 'error', true));

            return -1;
        }

        $stopOnError = false;
        $clonedImages = 0;

        $client = $this->clientProvider->getImageClient($orgSource);

        $hashes = $this->fetchData();

        $output->writeln('Reading images to be cloned from <info>'.$orgSource.'</info> to <info>'.$orgDest.'</info>');

        while (\count($hashes) > 0) {
            try {
                $output->writeln('Copying '.\count($hashes).' images from <comment>'.$orgSource.'</comment> to <comment>'.$orgDest.'</comment>');

                $hashes = $this->copyImages($orgDest, $orgSource, $hashes, $client);
                $total = \count($hashes['existing']) + \count($hashes['created']);
                $output->writeln($total.' images copied from <comment>'.$orgSource.'</comment> to <comment>'.$orgDest.'</comment> ('.
                    \count($hashes['existing']).' existing, '.\count($hashes['created']).' newly created).'
                );

                $clonedImages += $total;
            } catch (\Exception $e) {
                $output->writeln('');
                $output->writeln($this->formatterHelper->formatBlock([
                    'Error: Exception',
                    $e->getMessage(),
                ], 'error', true));
                if ($stopOnError) {
                    return -1;
                }
            }
            $hashes = $this->fetchData();
        }

        // Avoid further processing if no images have been loaded.
        if (0 == $clonedImages) {
            $output->write('No Image found in <info>'.$orgSource.'</info> organization.');

            return 0;
        }

        $output->writeln('');
        $output->writeln('Cloned images: <info>'.$clonedImages.'</info>');

        return 0;
    }

    /**
     * @param string          $destOrg
     * @param string          $sourceOrg
     * @param array           $hashes
     * @param OutputInterface $output
     *
     * @throws GuzzleException
     * @throws \RuntimeException
     * @throws \Exception
     *
     * @return array
     */
    protected function copyImages($destOrg, $sourceOrg, $hashes, \Rokka\Client\Image $client)
    {
        $result = $client->copySourceImages($hashes, $destOrg, true, $sourceOrg);
        if (0 === \count($result['existing']) && 0 === \count($result['created'])) {
            throw new \Exception('Some or all images not found on organization '.$sourceOrg.' !');
        }

        return $result;
    }

    /**
     * @param int   $limit
     * @param array $input
     */
    protected function fetchData(): array
    {
        $limit = 50;
        $hashes = [];
        $file = file_get_contents(__DIR__.'/../../hashes.txt');
        if (false === $file) {
            exit('Missing file');
        }

        if (empty($file)) {
            exit('EOF');
        }

        $input = preg_split('/\n|\r\n?/', $file);

        if (\count($input) < $limit) {
            $limit = \count($input);
        }

        for ($i = 0; $i < $limit; ++$i) {
            $hashes[] = $input[$i];
        }

        file_put_contents(__DIR__.'/../../hashes.txt', implode("\n", \array_slice($input, $limit)));

        return $hashes;
    }
}
