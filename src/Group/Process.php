<?php
/**
 * Created by PhpStorm.
 * User: Manlin
 * Date: 2019/9/19
 * Time: 下午3:54
 */
namespace EasySwoole\Kafka\Group;

use EasySwoole\Kafka\BaseProcess;
use EasySwoole\Kafka\Config\GroupConfig;
use EasySwoole\Kafka\Consumer\Assignment;
use EasySwoole\Kafka\Protocol;
use EasySwoole\Log\Logger;

class Process extends BaseProcess
{
    /**
     * Process constructor.
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->config = $this->getConfig();
        Protocol::init($this->config->getBrokerVersion());
        $this->getBroker()->setConfig($this->config);
    }

    /**
     * @return array
     * @throws \EasySwoole\Kafka\Exception\ConnectionException
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function getGroupBrokerId(): array
    {
        $broker  = $this->getBroker();

        $result = [];
        foreach ($this->brokerHost as $host) {
            $connect = $broker->getMetaConnect($host);
            if ($connect === null) {
                continue;
            }

            $config = $this->getConfig();
            $params = ['group_id' => $config->getGroupId()];

            $this->logger->log('Group coordinator start, params:' . json_encode($params));
            $requestData = Protocol::encode(Protocol::GROUP_COORDINATOR_REQUEST, $params);
            $data = $connect->send($requestData);

            $ret = Protocol::decode(Protocol::GROUP_COORDINATOR_REQUEST, substr($data, 8));

            $result[] = $ret;
        }

        return $result;
    }

    /**
     * @return array
     * @throws \EasySwoole\Kafka\Exception\ConnectionException
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function joinGroup(): array
    {
        $result = [];
        foreach ($this->brokerHost as $host) {
            $connect       = $this->getBroker()->getMetaConnect((string) $host);

            if ($connect === null) {
                continue;
            }

            $memberId = '';

            $params = [
                'group_id'          => $this->getConfig()->getGroupId(),
                'session_timeout'   => $this->getConfig()->getSessionTimeout(),
                'rebalance_timeout' => $this->getConfig()->getRebalanceTimeout(),
                'member_id'         => $memberId ?? '',// todo joinGroup接口返回的member_id
                'data'              => [
                    [
                        'protocol_name' => 'group',
                        'version'       => 0,
                        'subscription'  => ['test'],// todo
                        'user_data'     => '',
                    ],
                ],
            ];

            $requestData = Protocol::encode(Protocol::JOIN_GROUP_REQUEST, $params);
            $this->logger->log('Join group start, params:' . json_encode($params), Logger::LOG_LEVEL_INFO);
            $data = $connect->send($requestData);
            $ret = Protocol::decode(Protocol::JOIN_GROUP_REQUEST, substr($data, 8));

            $result[] = $ret;
        }

        return $result;
    }

    /**
     * @return array
     * @throws \EasySwoole\Kafka\Exception\ConnectionException
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function leaveGroup(): array
    {
        $broker = $this->getBroker();

        $result = [];
        foreach ($this->brokerHost as $host) {
            $connect = $broker->getMetaConnect($host);
            if ($connect === null) {
                continue;
            }

            $memberId = $this->getAssignment()->getMemberId();

            $params = [
                'group_id'          => $this->getConfig()->getGroupId(),
                'member_id'         => $memberId ?? '',// todo joinGroup接口返回的member_id
            ];

            $requestData = Protocol::encode(Protocol::LEAVE_GROUP_REQUEST, $params);
            $this->logger->log('Leave group start, params:' . json_encode($params), Logger::LOG_LEVEL_INFO);
            $data = $connect->send($requestData);
            $ret = Protocol::decode(Protocol::LEAVE_GROUP_REQUEST, substr($data, 8));

            $result[] = $ret;
        }

        return $result;
    }

    /**
     * @return array
     * @throws \EasySwoole\Kafka\Exception\ConnectionException
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function syncGroup(): array
    {
        $result     = [];
        foreach ($this->brokerHost as $host) {
            $connect = $this->getBroker()->getMetaConnect($host);

            if ($connect === null) {
                continue;
            }

            $assign       = $this->getAssignment();
            $memberId     = $assign->getMemberId(); // //todo joinGroup接口返回的member_id
            $generationId = $assign->getGenerationId(); //todo joinGroup接口返回的generation_id

            $assign->setAssignments([[
                'version' => 0,
                'member_id' => 'Easyswoole-kafka-d2a3bca8-6709-457c-8d6b-95fe7f95a107',
                'assignments' => [[
                    'topic_name' => 'test',
                    'partitions' => [
                        0
                    ],
                ]],
            ]]);

            $params = [
                'group_id'      => $this->getConfig()->getGroupId(),
                'generation_id' => $generationId ?? null,
                'member_id'     => $memberId,
                'data'          => $assign->getAssignments(),
            ];

            $requestData = Protocol::encode(Protocol::SYNC_GROUP_REQUEST, $params);
            $this->logger->log('Sync group start, params:' . json_encode($params));

            $data = $connect->send($requestData);
            $ret = Protocol::decode(Protocol::SYNC_GROUP_REQUEST, substr($data, 8));

            $result[] = $ret;
        }

        return $result;
    }

    /**
     * @return array
     * @throws \EasySwoole\Kafka\Exception\ConnectionException
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function describeGroups(): array
    {
        $result     = [];
        foreach ($this->brokerHost as $host) {
            $connect = $this->getBroker()->getMetaConnect($host);

            if ($connect === null) {
                continue;
            }

            $params = [
                'groups' => $this->config->getGroupId(),
            ];

            $requestData = Protocol::encode(Protocol::DESCRIBE_GROUPS_REQUEST, $params);
            $this->logger->log('Describe group start, params:' . json_encode($params));

            $data = $connect->send($requestData);
            $ret = Protocol::decode(Protocol::DESCRIBE_GROUPS_REQUEST, substr($data, 8));

            $result[] = $ret;
        }

        return $result;
    }

    /**
     * @return array
     * @throws \EasySwoole\Kafka\Exception\ConnectionException
     * @throws \EasySwoole\Kafka\Exception\Exception
     */
    public function listGroup(): array
    {
        $broker        = $this->getBroker();

        $result = [];
        foreach ($this->brokerHost as $host) {
            $connect       = $broker->getMetaConnect((string) $host);

            if ($connect === null) {
                continue;
            }

            $params = [];

            $requestData = Protocol::encode(Protocol::LIST_GROUPS_REQUEST, $params);
            $this->logger->log('List group start, params:' . json_encode($params), Logger::LOG_LEVEL_INFO);

            $data = $connect->send($requestData);
            $ret = Protocol::decode(Protocol::LIST_GROUPS_REQUEST, substr($data, 8));

            $result[] = $ret;
        }

        return $result;
    }

    /**
     * @return GroupConfig
     */
    protected function getConfig(): GroupConfig
    {
        return GroupConfig::getInstance();
    }

    /**
     * @return Assignment
     */
    protected function getAssignment(): Assignment
    {
        return Assignment::getInstance();
    }
}
