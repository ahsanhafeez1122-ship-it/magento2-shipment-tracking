<?php
declare(strict_types=1);

namespace AhsanHafeez\ShipmentTracking\Console\Command;

use AhsanHafeez\ShipmentTracking\Model\CarrierProvider;
use AhsanHafeez\ShipmentTracking\Model\TrackLinkResolver;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Track\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento tracking:audit --days=30
 *
 * Lists recent tracks that would go out without a working link: unknown carrier,
 * empty number, or a number that doesn't match the carrier's format (usually a typo).
 */
class AuditCommand extends Command
{
    private const OPTION_DAYS = 'days';
    private const OPTION_LIMIT = 'limit';

    public function __construct(
        private readonly CollectionFactory $trackCollectionFactory,
        private readonly TrackLinkResolver $linkResolver,
        private readonly CarrierProvider $carrierProvider,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('tracking:audit')
            ->setDescription('Find shipment tracks with no tracking link or a suspicious tracking number')
            ->addOption(self::OPTION_DAYS, null, InputOption::VALUE_REQUIRED, 'Look back this many days', '30')
            ->addOption(self::OPTION_LIMIT, null, InputOption::VALUE_REQUIRED, 'Show at most this many problems', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // Area already set.
        }

        foreach ($this->carrierProvider->getConfigErrors() as $error) {
            $output->writeln('<comment>Carrier config: ' . $error . '</comment>');
        }

        $days = max(1, (int) $input->getOption(self::OPTION_DAYS));
        $limit = max(1, (int) $input->getOption(self::OPTION_LIMIT));

        $collection = $this->trackCollectionFactory->create()
            ->addFieldToFilter('created_at', ['gteq' => gmdate('Y-m-d H:i:s', time() - $days * 86400)])
            ->setOrder('entity_id', 'DESC');

        $rows = [];
        $checked = 0;
        foreach ($collection as $track) {
            $checked++;
            $link = $this->linkResolver->resolve($track, null, (int) $track->getStoreId());

            $problem = match (true) {
                $link->number === '' => 'Empty tracking number',
                $link->url === null => 'Carrier not recognised: add an alias',
                !$link->numberLooksValid => 'Number does not match carrier format',
                default => null,
            };
            if ($problem === null) {
                continue;
            }

            $rows[] = [$track->getId(), $track->getOrderId(), $track->getCarrierCode(), $track->getTitle(), $link->number, $problem];
            if (count($rows) >= $limit) {
                break;
            }
        }

        if ($rows === []) {
            $output->writeln(sprintf('<info>Checked %d track(s) from the last %d day(s). Every one has a working link.</info>', $checked, $days));
            return Command::SUCCESS;
        }

        (new Table($output))
            ->setHeaders(['Track ID', 'Order ID', 'Carrier code', 'Title', 'Number', 'Problem'])
            ->setRows($rows)
            ->render();
        $output->writeln(sprintf('<comment>%d problem(s) in %d track(s) checked.</comment>', count($rows), $checked));

        return Command::FAILURE;
    }
}
