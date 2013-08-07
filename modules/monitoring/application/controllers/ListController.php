<?php

use Icinga\Web\ModuleActionController;
use Icinga\Web\Hook;
use Icinga\File\Csv;
use Monitoring\Backend;
use Icinga\Application\Benchmark;
use Icinga\Web\Widget\Tabs;

class Monitoring_ListController extends ModuleActionController
{
    protected $backend;
    private $compactView = null;

    public function init()
    {
        $this->view->tabs = $this->getTabs()
             ->activate($this->action_name)
             ->enableSpecialActions();
        $this->backend = Backend::getInstance($this->_getParam('backend'));
        $this->view->grapher = Hook::get('grapher');
    }

    public function hostsAction()
    {
        Benchmark::measure("hostsAction::query()");
        $this->compactView = "hosts-compact";
        $this->view->hosts = $this->query(
            'status',
            array(
                'host_icon_image',
                'host_name',
                'host_state',
                'host_address',
                'host_acknowledged',
                'host_output',
                'host_long_output',
                'host_in_downtime',
                'host_is_flapping',
                'host_state_type',
                'host_handled',
                'host_last_check',
                'host_last_state_change',
                'host_notifications_enabled',
                'host_unhandled_service_count',
                'host_action_url',
                'host_notes_url',
                'host_last_comment'
            )
        );
    }

    public function servicesAction()
    {
        $state_type = $this->_getParam('_statetype', 'soft');
        if ($state_type = 'soft') {
            $state_column = 'service_state';
            $state_change_column = 'service_last_state_change';
        } else {
            $state_column = 'service_hard_state';
            $state_change_column = 'service_last_hard_state_change';
        }
        $this->compactView = "services-compact";


        $this->view->services = $this->query('status', array(
            'host_name',
            'host_state',
            'host_state_type',
            'host_last_state_change',
            'host_address',
            'host_handled',
            'service_description',
            'service_display_name',
            'service_state' => $state_column,
            'service_in_downtime',
            'service_acknowledged',
            'service_handled',
            'service_output',
            'service_last_state_change' => $state_change_column,
            'service_icon_image',
            'service_long_output',
            'service_is_flapping',
            'service_state_type',
            'service_handled',
            'service_severity',
            'service_last_check',
            'service_notifications_enabled',
            'service_action_url',
            'service_notes_url',
            'service_last_comment'
        ));
        $this->inheritCurrentSortColumn();
    }

    public function hostgroupsAction()
    {
        $this->view->hostgroups = $this->backend->select()
            ->from('hostgroup', array(
            'hostgroup_name',
            'hostgroup_alias',
        ))->applyRequest($this->_request);
    }

    public function servicegroupsAction()
    {
        $this->view->servicegroups = $this->backend->select()
            ->from('servicegroup', array(
            'servicegroup_name',
            'servicegroup_alias',
        ))->applyRequest($this->_request);
    }

    public function contactgroupsAction()
    {
        $this->view->contactgroups = $this->backend->select()
            ->from('contactgroup', array(
            'contactgroup_name',
            'contactgroup_alias',
        ))->applyRequest($this->_request);
    }

    public function contactsAction()
    {
        $this->view->contacts = $this->backend->select()
            ->from('contact', array(
            'contact_name',
            'contact_alias',
            'contact_email',
            'contact_pager'
        ))->applyRequest($this->_request);
    }

    // TODO: Search helper playground
    public function searchAction()
    {
        $data = array(
            'service_description',
            'service_state',
            'service_acknowledged',
            'service_handled',
            'service_output',
            // '_host_satellite',
            'service_last_state_change'
            );
        echo json_encode($data);
        exit;
    }

    /**
     * Fetch the current downtimes and put them into the view
     * property 'downtimes'
     */
    public function downtimesAction()
    {
         $query = $this->backend->select()
            ->from('downtime',array(
                'host_name',
                'object_type',
                'service_description',
                'downtime_entry_time',
                'downtime_internal_downtime_id',
                'downtime_author_name',
                'downtime_comment_data',
                'downtime_duration',
                'downtime_scheduled_start_time',
                'downtime_scheduled_end_time',
                'downtime_is_fixed',
                'downtime_is_in_effect',
                'downtime_triggered_by_id',
                'downtime_trigger_time'
        ));
        if (!$this->_getParam('sort')) {
            $query->order('downtime_is_in_effect');
        }
        $this->view->downtimes = $query->applyRequest($this->_request);
        $this->inheritCurrentSortColumn();
    }

    protected function query($view, $columns)
    {
        $extra = preg_split(
            '~,~',
            $this->_getParam('extracolumns', ''),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $this->view->extraColumns = $extra;
        $query = $this->backend->select()
            ->from($view, array_merge($columns, $extra))
            ->applyRequest($this->_request);
        $this->handleFormatRequest($query);
        return $query;
    }

    protected function handleFormatRequest($query)
    {
        if ($this->compactView !== null && $this->_getParam("view", false) === "compact") {
            $this->_helper->viewRenderer($this->compactView);
        }

        if ($this->_getParam('format') === 'sql') {
            echo '<pre>'
                . htmlspecialchars(wordwrap($query->getQuery()->dump()))
                . '</pre>';
            exit;
        }
        if ($this->_getParam('format') === 'json'
            || $this->_request->getHeader('Accept') === 'application/json')
        {
            header('Content-type: application/json');
            echo json_encode($query->fetchAll());
            exit;
        }
        if ($this->_getParam('format') === 'csv'
            || $this->_request->getHeader('Accept') === 'text/csv') {
            Csv::fromQuery($query)->dump();
            exit;
        }
    }

    protected function getTabs()
    {
        $tabs = new Tabs();
        $tabs->add('services', array(
            'title'     => 'All services',
            'icon'      => 'img/classic/service.png',
            'url'       => 'monitoring/list/services',
        ));
        $tabs->add('hosts', array(
            'title'     => 'All hosts',
            'icon'      => 'img/classic/server.png',
            'url'       => 'monitoring/list/hosts',
        ));
        $tabs->add('downtimes', array(
            'title'     => 'Downtimes',
            'icon'      => 'img/classic/downtime.gif',
            'url'       => 'monitoring/list/downtimes',
        ));
/*
        $tabs->add('hostgroups', array(
            'title'     => 'Hostgroups',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/hostgroups',
        ));
        $tabs->add('servicegroups', array(
            'title'     => 'Servicegroups',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/servicegroups',
        ));
        $tabs->add('contacts', array(
            'title'     => 'Contacts',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/contacts',
        ));
        $tabs->add('contactgroups', array(
            'title'     => 'Contactgroups',
            'icon'      => 'img/classic/servers-network.png',
            'url'       => 'monitoring/list/contactgroups',
        ));
*/
        return $tabs;
    }


    /**
     * Let the current response inherit the used sort column by applying it to the
     * view property 'sort'
     */
    private function inheritCurrentSortColumn()
    {
        if ($this->_getParam('sort')) {
            $this->view->sort = $this->_getParam('sort');
        }
    }
}
