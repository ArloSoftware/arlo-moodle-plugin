<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace enrol_arlo\request;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use enrol_arlo\alert;
use enrol_arlo\manager;
use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Response;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class patch_request extends abstract_request {
    public function execute() {
        global $CFG;
        try {
            $requesturi = $this->requesturi;
            $schedule = $this->schedule;
            $client = new Client();
            $response = $client->request('patch', $requesturi, $this->headers, $this->body, $this->options);
            self::log($requesturi->getHost(), $requesturi->output(), $response->getStatusCode());
            // Update API status vars.
            set_config('apistatus', $response->getStatusCode(), 'enrol_arlo');
            set_config('apilastrequested', time(), 'enrol_arlo');
            set_config('apierrorcount', 0, 'enrol_arlo');
            return $response;
        } catch (RequestException $exception) {
            // Update error information.
            $errorcount = (int) $schedule->errorcount;
            $schedule->errorcount = ++$errorcount;
            $schedule->lasterror = $exception->getMessage();
            $schedule->lastpushtime = time();
            manager::update_scheduling_information($schedule);
            $apierrorcount = (int) get_config('enrol_arlo', 'apierrorcount');
            $status = $exception->getCode();
            $uri = (string) $exception->getRequest()->getUri();
            $extra = $exception->getResponse()->getBody()->getContents();
            // Update API status vars.
            set_config('apistatus', $status, 'enrol_arlo');
            set_config('apilastrequested', time(), 'enrol_arlo');
            set_config('apierrorcount', ++$apierrorcount, 'enrol_arlo');
            // Log the request.
            self::log($requesturi->getHost(), $uri, $status, $extra);
            // Alert.
            if ($status == 401 || $status == 403) {
                $params = array('url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsarlo');
                alert::create('error_invalidcredentials', $params)->send();
            }
            // Be nice return a empty response with http status set.
            $response = new Response();
            return $response->withStatus($status);
        }
    }
}