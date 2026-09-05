<?php
namespace App\Services;use App\Contracts\DnsResolver;
class SystemDnsResolver implements DnsResolver {public function resolve(string $host):array{if(filter_var($host,FILTER_VALIDATE_IP))return [$host];$records=dns_get_record($host,DNS_A|DNS_AAAA);$ips=[];foreach($records?:[] as $record){if(isset($record['ip']))$ips[]=$record['ip'];if(isset($record['ipv6']))$ips[]=$record['ipv6'];}return array_values(array_unique($ips));}}
