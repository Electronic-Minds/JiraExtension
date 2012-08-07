<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension\Service;

/**
 * Jira service
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class JiraService
{
    const MAX_ISSUES = 2147483647;

    private $soapClientClass;
    private $host;
    private $user;
    private $password;
    private $jql;
    private $soapClient;
    private $token;

    /**
     * Constructor
     *
     * @param string $soapClientClass SOAP client class name
     * @param string $host            Jira server base URL
     * @param string $user            Jira user ID
     * @param string $password        Jira user password
     * @param string $jql             JQL query
     * @param string $wsdlUrl         WSDL URL
     */
    public function __construct($soapClientClass, $host, $user, $password, $jql, $wsdlUrl)
    {
        if (substr($soapClientClass, 0, 1) !== '\\') {
            $soapClientClass = '\\' . $soapClientClass;
        }

        $this->soapClientClass = $soapClientClass;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->jql = $jql;
        $this->wsdlUrl = $wsdlUrl;
    }

    /**
     * Login to Jira
     */
    private function connect()
    {
        if ($this->soapClient) {
            return;
        }

        $class = $this->soapClientClass;

        $this->soapClient = new $class($this->host . $this->wsdlUrl, array('trace'=>true));
        $this->token = $this->soapClient->login($this->user, $this->password);
    }

    /**
     * Get JQL query
     *
     * @param integer $timestamp
     *
     * @return string
     */
    protected function getJql($timestamp)
    {
        if (!isset($timestamp)) {
            return $this->jql;
        }

        return $this->jql . " AND updated > '" . date('Y-m-d H:i', $timestamp) . "'";
    }

    /**
     * Fetch issues matching jql and resource
     *
     * @param integer $timestamp Optional timestamp for issues updated since the timestamp
     *
     * @return array
     *
     * {@internal the number of results is constrained by jira.search.views.max.limit
     *            and jira.search.views.max.unlimited.group JIRA properties }}
     */
    public function fetchIssues($timestamp = null)
    {
        $this->connect();

        $issues = $this->soapClient->getIssuesFromJqlSearch($this->token, $this->getJql($timestamp), self::MAX_ISSUES);

        return $issues;
    }

    /**
     * Fetch issue
     *
     * @param string $id Issue key
     *
     * @return string
     */
    public function fetchIssue($id)
    {
        $this->connect();

        return $this->soapClient->getIssue($this->token, $id);
    }

    /**
     * Add comment
     *
     * @param string $id   Issue key
     * @param string $body Comment body
     */
    public function postComment($id, $body)
    {
        $this->connect();

        $comment = array(
            'body' => $body,
        );

        $this->soapClient->addComment($this->token, $id, $comment);
    }

    /**
     * Get reopen action ID
     *
     * @param string $id Issue key
     *
     * @return string
     */
    private function getReopenActionId($id)
    {
        $actions = $this->soapClient->getAvailableActions($this->token, $id);

        foreach ($actions as $action) {
            if (strpos(strtolower($action->name), 'reopen') !== false) {
                return $action->id;
            }
        }
    }

    /**
     * Re-open issue
     *
     * @param string $id Issue key
     *
     * {@internal subject to workflow progression rules }}}
     */
    public function reopenIssue($id)
    {
        $this->connect();

        $action = $this->getReopenActionId($id);

        if (isset($action)) {
            $this->soapClient->progressWorkflowAction($this->token, $id, $action, array());
        }
    }

    /**
     * Get Jira issue from URL
     *
     * @param string $resource
     *
     * @return string|null
     */
    public function getIssue($resource)
    {
        $url = $this->host . '/browse/';

        if (strncmp($resource, $url, strlen($url)) === 0) {
            return substr($resource, strlen($url));
        }
    }

    /**
     * Get Jira URL for issue
     *
     * @param string $id Issue key
     *
     * @return string
     */
    public function getUrl($id)
    {
        return $this->host . '/browse/' . $id;
    }

    /**
     * Does URL match Jira URL?
     *
     * @param string $url
     *
     * @return boolean
     */
    public function urlMatches($url)
    {
        return strncmp($url, $this->host, strlen($this->host)) === 0;
    }
}
