<?php

declare(strict_types=1);

namespace Daycry\Doctrine\Config;

use CodeIgniter\Config\BaseConfig;
use DoctrineExtensions\Query\Mysql\AnyValue;
use DoctrineExtensions\Query\Mysql\Binary;
use DoctrineExtensions\Query\Mysql\BitCount;
use DoctrineExtensions\Query\Mysql\BitXor;
use DoctrineExtensions\Query\Mysql\Cast;
use DoctrineExtensions\Query\Mysql\Ceil;
use DoctrineExtensions\Query\Mysql\Collate;
use DoctrineExtensions\Query\Mysql\ConcatWs;
use DoctrineExtensions\Query\Mysql\ConvertTz;
use DoctrineExtensions\Query\Mysql\CountIf;
use DoctrineExtensions\Query\Mysql\Crc32;
use DoctrineExtensions\Query\Mysql\Date;
use DoctrineExtensions\Query\Mysql\DateFormat;
use DoctrineExtensions\Query\Mysql\Day;
use DoctrineExtensions\Query\Mysql\DayName;
use DoctrineExtensions\Query\Mysql\Extract;
use DoctrineExtensions\Query\Mysql\Field;
use DoctrineExtensions\Query\Mysql\FindInSet;
use DoctrineExtensions\Query\Mysql\Floor;
use DoctrineExtensions\Query\Mysql\FromUnixtime;
use DoctrineExtensions\Query\Mysql\GroupConcat;
use DoctrineExtensions\Query\Mysql\Hex;
use DoctrineExtensions\Query\Mysql\Hour;
use DoctrineExtensions\Query\Mysql\IfElse;
use DoctrineExtensions\Query\Mysql\IfNull;
use DoctrineExtensions\Query\Mysql\LastDay;
use DoctrineExtensions\Query\Mysql\Log;
use DoctrineExtensions\Query\Mysql\Log10;
use DoctrineExtensions\Query\Mysql\Log2;
use DoctrineExtensions\Query\Mysql\MakeDate;
use DoctrineExtensions\Query\Mysql\MatchAgainst;
use DoctrineExtensions\Query\Mysql\Md5;
use DoctrineExtensions\Query\Mysql\Minute;
use DoctrineExtensions\Query\Mysql\Month;
use DoctrineExtensions\Query\Mysql\MonthName;
use DoctrineExtensions\Query\Mysql\Now;
use DoctrineExtensions\Query\Mysql\NullIf;
use DoctrineExtensions\Query\Mysql\PeriodDiff;
use DoctrineExtensions\Query\Mysql\Power;
use DoctrineExtensions\Query\Mysql\Quarter;
use DoctrineExtensions\Query\Mysql\Rand;
use DoctrineExtensions\Query\Mysql\Regexp;
use DoctrineExtensions\Query\Mysql\Replace;
use DoctrineExtensions\Query\Mysql\Round;
use DoctrineExtensions\Query\Mysql\Second;
use DoctrineExtensions\Query\Mysql\Sha1;
use DoctrineExtensions\Query\Mysql\Sha2;
use DoctrineExtensions\Query\Mysql\Soundex;
use DoctrineExtensions\Query\Mysql\Std;
use DoctrineExtensions\Query\Mysql\StdDev;
use DoctrineExtensions\Query\Mysql\StrToDate;
use DoctrineExtensions\Query\Mysql\SubstringIndex;
use DoctrineExtensions\Query\Mysql\TimeDiff;
use DoctrineExtensions\Query\Mysql\TimestampAdd;
use DoctrineExtensions\Query\Mysql\TimestampDiff;
use DoctrineExtensions\Query\Mysql\TimeToSec;
use DoctrineExtensions\Query\Mysql\Truncate;
use DoctrineExtensions\Query\Mysql\Unhex;
use DoctrineExtensions\Query\Mysql\UnixTimestamp;
use DoctrineExtensions\Query\Mysql\UtcTimestamp;
use DoctrineExtensions\Query\Mysql\UuidShort;
use DoctrineExtensions\Query\Mysql\Variance;
use DoctrineExtensions\Query\Mysql\Week;
use DoctrineExtensions\Query\Mysql\Year;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonArray;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonArrayAgg;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonArrayAppend;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonArrayInsert;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonContains;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonContainsPath;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonDepth;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonExtract;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonInsert;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonKeys;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonLength;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonMerge;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonMergePatch;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonMergePreserve;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonObject;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonObjectAgg;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonOverlaps;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonPretty;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonQuote;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonRemove;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonReplace;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonSearch;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonSet;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonType;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonUnquote;
use Scienta\DoctrineJsonFunctions\Query\AST\Functions\Mysql\JsonValid;

class Doctrine extends BaseConfig
{
    public bool $setAutoGenerateProxyClasses = ENVIRONMENT === 'development';

    /**
     * @var list<string>
     */
    public array $entities = [APPPATH . 'Models/Entity'];

    public string $proxies          = APPPATH . 'Models/Proxies';
    public string $proxiesNamespace = 'DoctrineProxies';

    /**
     * Enable native lazy objects (PHP 8.4+).
     * When true, Doctrine uses PHP native lazy objects instead of generated proxy classes.
     * Set to false to keep using generated proxies (required for PHP < 8.4).
     */
    public bool $proxyFactory = true;

    public bool $queryCache               = true;
    public string $queryCacheNamespace    = 'doctrine_queries';
    public bool $resultsCache             = true;
    public string $resultsCacheNamespace  = 'doctrine_results';
    public bool $metadataCache            = true;
    public string $metadataCacheNamespace = 'doctrine_metadata';

    /**
     * Ex: attribute,xml
     */
    public string $metadataConfigurationMethod = 'attribute';

    /**
     * If metadataConfigurationMethod is 'xml'
     */
    public bool $isXsdValidationEnabled = false;

    /**
     * Second-Level Cache toggle.
     * When true, SLC uses the framework cache backend configured in `Config\Cache`.
     */
    public bool $secondLevelCache = false;

    /**
     * Enable Second-Level Cache statistics logging (hits/misses/puts).
     * When true, Doctrine will collect SLC statistics via a cache logger.
     */
    public bool $secondLevelCacheStatistics = false;

    /**
     * Second-Level Cache default lifetime (TTL) in seconds.
     * - null: inherit framework cache TTL from `Config\Cache`.
     * - 0:    no expiration (entries persist until explicitly invalidated).
     * - >0:   use this TTL for SLC entries and regions.
     */
    public ?int $secondLevelCacheTtl = null;

    /**
     * Custom DQL string functions (beberlei/doctrineextensions + user-defined).
     * Remove or override any entry to disable or replace a function.
     *
     * @var array<string, class-string>
     */
    public array $customStringFunctions = [
        'DATE_FORMAT'     => DateFormat::class,
        'IF'              => IfElse::class,
        'IFNULL'          => IfNull::class,
        'NULLIF'          => NullIf::class,
        'CONCAT_WS'       => ConcatWs::class,
        'GROUP_CONCAT'    => GroupConcat::class,
        'REPLACE'         => Replace::class,
        'FIELD'           => Field::class,
        'FIND_IN_SET'     => FindInSet::class,
        'CAST'            => Cast::class,
        'BINARY'          => Binary::class,
        'COLLATE'         => Collate::class,
        'REGEXP'          => Regexp::class,
        'HEX'             => Hex::class,
        'UNHEX'           => Unhex::class,
        'MD5'             => Md5::class,
        'SHA1'            => Sha1::class,
        'SHA2'            => Sha2::class,
        'SOUNDEX'         => Soundex::class,
        'UUID_SHORT'      => UuidShort::class,
        'MATCH_AGAINST'   => MatchAgainst::class,
        'SUBSTRING_INDEX' => SubstringIndex::class,
        'ANY_VALUE'       => AnyValue::class,
    ];

    /**
     * Custom DQL numeric functions.
     *
     * @var array<string, class-string>
     */
    public array $customNumericFunctions = [
        'ROUND'         => Round::class,
        'FLOOR'         => Floor::class,
        'CEIL'          => Ceil::class,
        'POWER'         => Power::class,
        'RAND'          => Rand::class,
        'LOG'           => Log::class,
        'LOG2'          => Log2::class,
        'LOG10'         => Log10::class,
        'TRUNCATE'      => Truncate::class,
        'VARIANCE'      => Variance::class,
        'STD'           => Std::class,
        'STDDEV'        => StdDev::class,
        'BIT_COUNT'     => BitCount::class,
        'BIT_XOR'       => BitXor::class,
        'COUNT_IF'      => CountIf::class,
        'CRC32'         => Crc32::class,
        'PERIOD_DIFF'   => PeriodDiff::class,
        'TIMESTAMPDIFF' => TimestampDiff::class,
        'TIME_TO_SEC'   => TimeToSec::class,
    ];

    /**
     * Custom DQL datetime functions.
     * Note: DATE_ADD, DATE_DIFF, DATE_SUB are built-in to Doctrine ORM 3.x and excluded here.
     *
     * @var array<string, class-string>
     */
    public array $customDatetimeFunctions = [
        'DATE'           => Date::class,
        'NOW'            => Now::class,
        'TIMEDIFF'       => TimeDiff::class,
        'TIMESTAMPADD'   => TimestampAdd::class,
        'CONVERT_TZ'     => ConvertTz::class,
        'FROM_UNIXTIME'  => FromUnixtime::class,
        'UNIX_TIMESTAMP' => UnixTimestamp::class,
        'UTC_TIMESTAMP'  => UtcTimestamp::class,
        'STR_TO_DATE'    => StrToDate::class,
        'LAST_DAY'       => LastDay::class,
        'MAKE_DATE'      => MakeDate::class,
        'EXTRACT'        => Extract::class,
        'MONTH'          => Month::class,
        'YEAR'           => Year::class,
        'DAY'            => Day::class,
        'HOUR'           => Hour::class,
        'MINUTE'         => Minute::class,
        'SECOND'         => Second::class,
        'WEEK'           => Week::class,
        'QUARTER'        => Quarter::class,
        'DAYNAME'        => DayName::class,
        'MONTHNAME'      => MonthName::class,
    ];

    /**
     * Custom JSON DQL functions (scienta/doctrine-json-functions).
     * Covers MySQL 5.7+ / MariaDB shared functions.
     * Remove or override any entry to disable or replace a function.
     * Replaces the 3 JSON functions previously in $customStringFunctions (beberlei).
     *
     * @var array<string, class-string>
     */
    public array $customJsonFunctions = [
        'JSON_ARRAY'          => JsonArray::class,
        'JSON_ARRAY_APPEND'   => JsonArrayAppend::class,
        'JSON_ARRAY_INSERT'   => JsonArrayInsert::class,
        'JSON_ARRAYAGG'       => JsonArrayAgg::class,
        'JSON_CONTAINS'       => JsonContains::class,
        'JSON_CONTAINS_PATH'  => JsonContainsPath::class,
        'JSON_DEPTH'          => JsonDepth::class,
        'JSON_EXTRACT'        => JsonExtract::class,
        'JSON_INSERT'         => JsonInsert::class,
        'JSON_KEYS'           => JsonKeys::class,
        'JSON_LENGTH'         => JsonLength::class,
        'JSON_MERGE'          => JsonMerge::class,
        'JSON_MERGE_PATCH'    => JsonMergePatch::class,
        'JSON_MERGE_PRESERVE' => JsonMergePreserve::class,
        'JSON_OBJECT'         => JsonObject::class,
        'JSON_OBJECTAGG'      => JsonObjectAgg::class,
        'JSON_OVERLAPS'       => JsonOverlaps::class,
        'JSON_PRETTY'         => JsonPretty::class,
        'JSON_QUOTE'          => JsonQuote::class,
        'JSON_REMOVE'         => JsonRemove::class,
        'JSON_REPLACE'        => JsonReplace::class,
        'JSON_SEARCH'         => JsonSearch::class,
        'JSON_SET'            => JsonSet::class,
        'JSON_TYPE'           => JsonType::class,
        'JSON_UNQUOTE'        => JsonUnquote::class,
        'JSON_VALID'          => JsonValid::class,
    ];

    /**
     * Custom DBAL type mappings registered on the database platform.
     * These replace the hardcoded enum/set mappings and are re-applied on reOpen().
     * Key = native DB type, value = Doctrine type name.
     *
     * @var array<string, string>
     */
    public array $customTypeMappings = [
        'enum' => 'string',
        'set'  => 'string',
    ];
}
