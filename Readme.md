# Task
>Implement a simple PHP service (no frameworks) that serves as a full-text search engine. It takes blocks of text as documents and allows to search in them.

# Requirements:

- The service should store all data into Redis.
- POST /document/XXX Saves a text document with ID XXX
- GET /document/XXX Returns the text document with ID XXX
- Documents are just text (no fields to parse)
- GET /search?q={word} Returns the list of IDs from documents with content that match the given keyword (single word search)
- DELETE /document/XXX Deletes document with ID XXX

# Extra:
- GET /search?q={word1 word2 ... wordN} Returns the list of document IDs that match all the keywords.

>The service should be optimized for search speed and should be able to handle thousands of documents without significant performance degradation. 
