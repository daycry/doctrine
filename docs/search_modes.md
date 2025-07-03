# DataTables Search Modes

DataTables integration supports several search modes for flexible querying.

| Mode                | Pattern                  | Description                                                                                       |
|---------------------|-------------------------|---------------------------------------------------------------------------------------------------|
| LIKE '…%'           | [*%]searchTerm          | LIKE '…%' search; the start of the search term must match a value in the column.                  |
| LIKE '%…%'          | [%%]searchTerm          | LIKE '%…%' search; any part of the search term must match a value in the column.                  |
| Equality            | [=]searchTerm           | = … search; the search term must exactly match a value in the column.                             |
| != (No Equality)    | [!=]searchTerm          | != … search; the search term must not exactly match a value in the column.                        |
| Greater Than        | [>]searchTerm           | > … search; the search term must be smaller than a value in the column.                           |
| Smaller Than        | [<]searchTerm           | < … search; the search term must be greater than a value in the column.                           |
| IN                  | [IN]searchTerm,...      | IN(…) search; one of the comma-separated search terms must exactly match a value in the column.   |
| OR                  | [OR]searchTerm,...      | Multiple OR-connected LIKE('%…%') searches. Any term must match a fragment in the column.         |
| BETWEEN             | [><]searchTerm,searchTerm | BETWEEN … AND … search. Both search terms must be separated with a comma.                        |

Prefixes are case-insensitive (IN, in, OR, or). Provided search terms are trimmed.
