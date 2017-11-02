<?php

require_once 'api.php';
require 'redis.php';

class RedisApi extends Api
{
    /**
     * Redis connection
     * @var RedisClient
     */
    private $redisClient;

    /**
     * RedisApi constructor.
     * @param $request
     * @param $origin
     */
    public function __construct($request, $origin)
    {
        parent::__construct($request);
        $this->redisClient = new RedisClient();
    }

    /**
     * Function to process set, get and delete document by id
     * @return string
     */
    protected function document($args)
    {
        if (count($this->args) != 1) {
            return $this->headerAndResponse("Number of arguments for this endpoint need to be equal to 1", 404);
        } else {
            $key = $args[0];

            switch ($this->method) {
                case 'GET':
                    $result = $this->redisClient->rawCommand('get', ['DocumentKey:' . $key]);
                    $this->header(200);
                    return $this->response($result);
                case 'POST':
                    $data = file_get_contents('php://input');
                    if (!empty($data)) {
                        $exists = $this->redisClient->rawCommand('exists', ["DocumentKey:$key"]);
                        //Checking if a document with id to save was'n deleted before
                        $wasDeleted = $this->redisClient->rawCommand('sIsMember', ["deletedKeys", $key]);
                        if (!$exists && !$wasDeleted) {
                            //Remove all punctuation signs and transform to lowercase
                            $text = strtolower($this->stripPunctuation($data));
                            //Explode all the word into array
                            $words = array_unique(explode(" ", $text));
                            //Foreach word we create a set where we push the key of created document
                            foreach ($words as $word) {
                                $this->redisClient->rawCommand("sAdd", ["word:$word", $key]);
                            }
                            $this->redisClient->rawCommand("set", ["DocumentKey:$key", $data]);
                            return $this->headerAndResponse("Document saved", 404);
                            return 'Document saved';
                        } else {
                            return $this->headerAndResponse("A document with this id was saved before", 404);
                        }

                    } else {
                        return $this->headerAndResponse("Only accepts GET and POST requests", 404);
                    }
                case 'DELETE':
                    $result = $this->redisClient->rawCommand("exists", ["DocumentKey:$key"]);
                    if ($result) {
                        $this->redisClient->delete("DocumentKey:$key");
                        $this->redisClient->rawCommand("sAdd", ["deletedKeys", $key]);
                        return $this->headerAndResponse("Document with ID $key deleted");
                    } else {
                        return $this->headerAndResponse("Document with ID $key doesn't exists", 404);
                    }
                default:
                    $this->header(404);
                    return $this->response("Only accepts GET, POST and DELETE requests");
            }
        }
    }

    /**
     * Function to return search result
     * @return string
     */
    protected function search()
    {
        if ($this->method == 'GET') {
            $search = $_GET['q'];
            if (!is_array($search)) {
                $search = explode(' ', $search);
            }
            if (!empty($search)) {
                array_walk($search, function(&$value, $key) { $value = "word:$value"; } );
                $commonKeys = $this->redisClient->rawCommand('sMembers', [array_shift($search)]);
                foreach ($search as $searchWord) {
                    $wordKeys = $this->redisClient->rawCommand('sMembers', [$searchWord]);
                    $commonKeys = array_intersect($commonKeys, $wordKeys);
                }
                $deletedKeys = $this->redisClient->rawCommand("sMembers", ["deletedKeys"]);
                //Do not display words what was in deleted documents
                $result = array_diff($commonKeys, $deletedKeys);
                return $this->headerAndResponse($result);
            } else {
                return $this->headerAndResponse("No search trasnmited", 404);
            }
        } else {
            return $this->headerAndResponse("Only accepts GET, POST and DELETE requests", 404);
        }
    }

    /**
     * Function to remove all the punctuation signs from string
     * @param $string
     * @return string
     */
    private function stripPunctuation($string)
    {
        return trim(preg_replace('#[^a-zA-Z0-9- ]#', '', $string));
    }
}