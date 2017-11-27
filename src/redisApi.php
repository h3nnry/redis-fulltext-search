<?php


use Api;
use RedisClient;

class RedisApi extends Api
{
    /**
     * Redis connection
     * @var RedisClient
     */
    private $redisClient;

    /**
     * @var string
     */
    private $head = "";
    /**
     * @var int
     */
    private $status=200;

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
            $this->setHeadAndStatus("Number of arguments for this endpoint need to be equal to 1", 400);
        } else {
            $key = $args[0];

            switch ($this->method) {
                case 'GET':
                    $exists = $this->redisClient->rawCommand('exists', ["DocumentKey:$key"]);
                    if ($exists) {
                        $result = $this->redisClient->rawCommand('get', ['DocumentKey:' . $key]);
                        $this->setHeadAndStatus($result, 200);
                    } else {
                        $this->setHeadAndStatus("Document with ID $key doesn't exists", 404);
                    }
                    break;
                case 'POST':
                    $data = file_get_contents('php://input');
                    if (!empty($data)) {
                        $exists = $this->redisClient->rawCommand('exists', ["DocumentKey:$key"]);
                        //Checking if a document with id to save was'n deleted before
                        if ($exists) {
                            $this->deleteAllWordsAndDocument($key);
                        }
                        $this->saveAllWordsAndDocument($data, $key);
                        $this->setHeadAndStatus("Document saved", 201);
                    } else {
                        $this->setHeadAndStatus("Only accepts GET and POST requests", 405);
                    }
                    break;
                case 'DELETE':
                    $exists = $this->redisClient->rawCommand("exists", ["DocumentKey:$key"]);
                    if ($exists) {
                        $this->deleteAllWordsAndDocument($key);
                        $this->setHeadAndStatus("Document with ID $key deleted", 201);
                    } else {
                        $this->setHeadAndStatus("Document with ID $key doesn't exists", 404);
                    }
                    break;
                default:
                    $this->setHeadAndStatus("Only accepts GET, POST and DELETE requests", 405);
                    break;
            }
            return $this->headerAndResponse($this->head, $this->status);
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
                array_walk($search, function (&$value, $key) {
                    $value = "word:$value";
                });
                $commonKeys = $this->redisClient->rawCommand('sMembers', [array_shift($search)]);
                foreach ($search as $searchWord) {
                    $wordKeys = $this->redisClient->rawCommand('sMembers', [$searchWord]);
                    $commonKeys = array_intersect($commonKeys, $wordKeys);
                }
                $this->setHeadAndStatus(array_values($commonKeys), 200);
            } else {
                $this->setHeadAndStatus("No search trasnmited", 400);
            }
        } else {
            $this->setHeadAndStatus("Only accepts GET, POST and DELETE requests", 405);
        }
        return $this->headerAndResponse($this->head, $this->status);
    }

    /**
     * Function to remove all the punctuation signs from string
     * @param $string
     * @return string
     */
    private function stripPunctuation($string)
    {
        return strtolower(trim(preg_replace('/\pP+/', ' ', $string)));
    }

    /**
     * @param $key
     * @return bool
     */
    private function deleteAllWordsAndDocument($key)
    {
        try {
            $result = $this->redisClient->rawCommand('get', ['DocumentKey:' . $key]);

            $words = $this->textToArray($result);
            foreach ($words as $word) {
                $this->redisClient->rawCommand("sRem", ["word:$word", $key]);
            }
            $this->redisClient->delete("DocumentKey:$key");
        } catch (Exception $exception) {
            return $this->headerAndResponse("Error occured", 400);
        }
    }

    /**
     * @param $data
     * @param $key
     */
    private function saveAllWordsAndDocument($data, $key)
    {
        $words = $this->textToArray($data);
        foreach ($words as $word) {
            if (strlen($word) > 0) {
                $this->redisClient->rawCommand("sAdd", ["word:$word", $key]);
            }
        }
        $this->redisClient->rawCommand("set", ["DocumentKey:$key", $data]);
    }

    /**
     * @param $data
     * @return array
     */
    private function textToArray($data)
    {

        //Remove all punctuation signs and transform to lowercase
        $text = $this->stripPunctuation($data);

        //Explode all the word into array
        $words = array_unique(explode(" ", $text));

        return $words;
    }

    /**
     * @param $head
     * @param $status
     */
    private function setHeadAndStatus($head, $status)
    {
        $this->head = $head;
        $this->status = $status;
    }
}
