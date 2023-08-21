#!/usr/bin/env php
<?php

function sort_length($a, $b) {
	if (strlen($a) !== strlen($b)) {
		return strlen($a) - strlen($b);
	}
	return strcmp($a, $b);
}

function gcloud_dns($p, $domains) {
	$zs = explode("\n", trim(shell_exec("gcloud beta dns --project={$p} managed-zones list | cut '-d ' -f 1 | grep -v NAME")));
	if (!empty($zs)) {
		$zs = array_flip($zs);
	}

	foreach ($domains as $d => $nz) {
		$idn = idn_to_ascii($d, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
		echo "# Domain: {$d} ({$idn})\n";
		$dz = str_replace('.', '-', $idn);
		if (!array_key_exists($dz, $zs)) {
			echo "gcloud beta dns --project={$p} managed-zones create {$dz} --description='' --dns-name='{$idn}.' --visibility='public' --dnssec-state='off'\n";
		}

		while (array_key_exists('%template', $nz)) {
			$t = $nz['%template'];
			unset($nz['%template']);
			if (empty($GLOBALS['-templates'][$t])) {
				die("No such template {$t}!\n");
			}
			foreach ($GLOBALS['-templates'][$t] as $k => $v) {
				if (!empty($nz[$k])) {
					$nz[$k] = array_merge_recursive($v, $nz[$k]);
				}
				else {
					$nz[$k] = $v;
				}
			}
		}

		uksort($nz, 'sort_length');

		$zone = '';
		foreach ($nz as $domain => $vs) {
			foreach ($vs as $type => $values) {
				foreach ($values as $v) {
					if ($type === 'TXT') {
						if (strlen($v) > 255) {
							$v = implode('" "', str_split($v, 255));
						}
						$v = '"'.$v.'"';
					}
					$zone .= "$domain 43200 IN $type $v\n";
				}
			}
		}

		$zone = str_replace('%domain', $idn, $zone);
		file_put_contents("gcloud-dns-zones/{$dz}.zone", $zone);

		echo "gcloud beta dns --project={$p} record-sets import --zone={$dz} --delete-all-existing --zone-file-format gcloud-dns-zones/{$dz}.zone\n";
		echo "\n";
	}
}



$GLOBALS['-templates'] = [
	'mono-base' => [
		'%domain.' => [
			'A' => ['138.201.253.104'],
			'AAAA' => ['2a01:4f8:173:2467::2'],
			'MX' => ['1 aspmx.l.google.com.', '5 alt1.aspmx.l.google.com.', '5 alt2.aspmx.l.google.com.', '10 aspmx2.googlemail.com.', '10 aspmx3.googlemail.com.'],
			'TXT' => ['v=spf1 include:_spf.google.com a mx ptr ip4:138.201.253.104 ip6:2a01:4f8:173:2467::2 ~all'],
			],
		'*.%domain.' => [
			'CNAME' => ['%domain.'],
			],
		'_dmarc.%domain.' => [
			'TXT' => ['v=DMARC1; p=none; rua=mailto:dmarc-reports@gramtrans.com'],
			],
		],
	'mono-full' => [
		'%template' => 'mono-base',
		'%domain.' => [
			'TXT' => ['google-site-verification=CrAP2tqLhqfY6-_WefRsGo2EvDMzDHjD9kXOIp8XsIE', 'google-site-verification=MCifYavgq4UWewO0u_Te5x48vcWBqlgNpO9vQQsq9OE', 'google-site-verification=TzH_D5fsoAUlxhsweuA0tWhykdWWGnaPO_-hK-78fCM', 'google-site-verification=UYVtyRkskD3vMCAhLHOfWMh5gBRudFfVa2YfVgnsK2g'],
			],
		],

	'wolf-base' => [
		'%domain.' => [
			'A' => ['5.9.18.201'],
			'AAAA' => ['2a01:4f8:160:92cf::2'],
			],
		'*.%domain.' => [
			'CNAME' => ['%domain.'],
			],
		'_dmarc.%domain.' => [
			'TXT' => ['v=DMARC1; p=none; rua=mailto:dmarc-reports@tinodidriksen.com'],
			],
		],
	'wolf-mx' => [
		'%template' => 'wolf-base',
		'%domain.' => [
			'MX' => ['10 mail.projectjj.com.'],
			'TXT' => ['google-site-verification=dDCWHjXgRUCemnG36GYPWNtUIa0V_5FL2nC4J4jAB9g', 'v=spf1 include:_spf.google.com include:projectjj.com ~all'],
			],
		],
	'wolf-full' => [
		'%template' => 'wolf-base',
		'%domain.' => [
			'MX' => ['1 aspmx.l.google.com.', '5 alt1.aspmx.l.google.com.', '5 alt2.aspmx.l.google.com.', '10 aspmx2.googlemail.com.', '10 aspmx3.googlemail.com.'],
			'TXT' => ['google-site-verification=1ahGxvUnxo4W5CyHd8vMjAELNBE9QF79xfxVwV6O3gA', 'google-site-verification=2oXN9GpfpTIZYK0AQDcfwFtTE54mp03JBNlFuvgG5CI', 'google-site-verification=B7Q6_nLnWkxNP4-1GXWJttnEi-gUMgB6d4VupkMzNfk', 'google-site-verification=ecAjFb7DKAdEhCwG0AycYyDYAUB8beDVhgtG1ZP6-Xk', 'google-site-verification=UqSbzNpdCniZ6wieb68hmvC22RPgnjRjuLLZSnoKYrM', 'v=spf1 include:_spf.google.com include:projectjj.com ~all'],
			],
		],

	'apertium-base' => [
		'%domain.' => [
			'A' => ['144.76.217.21'],
			'AAAA' => ['2a01:4f8:200:900d::2'],
			'MX' => ['10 apertium.org.'],
			'TXT' => ['v=spf1 include:_spf.google.com a mx ptr a:apertium.org a:apertium.com ip4:144.76.217.21 ip6:2a01:4f8:200:900d::2/64 ~all'],
			],
		'dev.%domain.' => [
			'A' => ['78.46.22.15'],
			'AAAA' => ['2a01:4f8:201:2318::2'],
			],
		'*.%domain.' => [
			'CNAME' => ['%domain.'],
			],
		'_dmarc.%domain.' => [
			'TXT' => ['v=DMARC1; p=none; rua=mailto:dmarc-reports@tinodidriksen.com'],
			],
		],

	'oqaa-base' => [
		'%domain.' => [
			'A' => ['144.217.254.26'],
			'AAAA' => ['2607:5300:203:101a::'],
			'MX' => ['1 aspmx.l.google.com.', '5 alt1.aspmx.l.google.com.', '5 alt2.aspmx.l.google.com.', '10 aspmx2.googlemail.com.', '10 aspmx3.googlemail.com.'],
			'TXT' => ['google-site-verification=5FeNLM2s9b6Bph8DkE6r1mBea4tMYVLQlbqn2AHaNR8', 'google-site-verification=5FpaqZer98kijV9kVpE2xDQql-9I0kRk0g3uKC08U3c', 'google-site-verification=AMeO6Haw0veXy8zJr7cCeUVDq_68feKlDXtWkChNRMs', 'google-site-verification=oZCB-UFuH-R40PJ5xGq5u4fj139WHLMiBGSM7WSRXnQ', 'google-site-verification=U3Ao7JFK34W3ggTbWInlY89uQ7h6lFagG6NN-2xkOTs', 'v=spf1 include:_spf.google.com include:projectjj.com ~all'],
			],
		'*.%domain.' => [
			'CNAME' => ['%domain.'],
			],
		'_dmarc.%domain.' => [
			'TXT' => ['v=DMARC1; p=none; rua=mailto:tino+dmarc@oqaasileriffik.gl'],
			],
		],
	];

$GLOBALS['-projects'] = [
	'grammarsoft' => [
		'commatizer.com' => [
			'%template' => 'mono-full',
			'google-engcom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsi2oLXja51BvJq7mvpsOkocTVAsqy8EDo1eyFFN6ERH9TgXZijU2oTiCznuVnAaa45MFBYi68fV7ggOfmVlcTc+mSVCVhqBTm4BdQopWd3WbPMGyqrsqcXl6kN6yIukufi6xfnmPlRllD1EWN+SRnLvzCdePgYIJNg00D9abwdADJiPhfw/HzK7RYMBhos7szXdZmoA9gFQVSWGXxHaVohVTtgAooZeyKadlNOy4Bni0ttBGk61XdixJwA0PeWjdj7VHWX0bgrLPAXvZbWcqof63e0FyAIhsWPk4HOl8CS5BO5ag8OA4+VczXuknMPDE7ipSL0gEQGAVJHfyhVGluQIDAQAB'],
				],
			],
		'deepdict.com' => [
			'%template' => 'mono-full',
			],
		'grammarsoft.com' => [
			'%template' => 'mono-full',
			'google-gsoft._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArxE2jDUIItqyxl1s5Vo5y9El/x7uO84C1KTfjzBWhj716Lkx5TegBrXTG1ls4F1zAPPCQwDEswg4U+uS4OmypmbCEeBGvPHIfZtauRI2twNweN5fDAR6+5Biuyhs+SSrsIEUkympheQHe6iHpA8cgGXPmDmI5uFXWWB6CS+lO7d16okMyDZi5qqvP8o0bEnicPhcTq17BLbvz5Fl0FND/6auOlUMLY0zJQGb0UGoa3A6zga9ZBin0WEwIZhm3QZhMi9aQTfLovszrwCCf8xLtS1XJhCcexrwSZ3TV07WT8fIZ6z+8cjgQRxDyoy/U763ramUlmF2JfEDd2RU9aWfAQIDAQAB'],
				],
			],
		'gramtrans.com' => [
			'%template' => 'mono-base',
			'%domain.' => [
				'TXT' => ['google-site-verification=cSnqXZpIDHC_saJZ3k4f_Y8I66mQa7z2Sq7SPhN8qLY'],
				],
			'mono.%domain.' => [
				'A' => ['138.201.253.104'],
				'AAAA' => ['2a01:4f8:173:2467::2'],
				'MX' => ['10 mono.gramtrans.com.'],
				'TXT' => ['v=spf1 include:_spf.google.com a mx ptr ip4:138.201.253.104 ip6:2a01:4f8:173:2467::2 ~all'],
				],
			'trx.%domain.' => [
				'MX' => ['10 mono.gramtrans.com.'],
				],
			'backends.%domain.' => [
				'A' => ['176.9.7.122'],
				'AAAA' => ['2a01:4f8:141:504b::2'],
				],
			'_github-challenge-grammarsoft.%domain.' => [
				'TXT' => ['5e21ad7ad0'],
				],
			'google._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlLZqIspVscRXjhOaYonjkrEOJF/Icegyj0i9wQDgYxZ0LcQ9r1a5Psur8ik6CMsnV8/iRI8S1n7ssMHEtc1Z+hGmOa0nrWInPlS/G0sqMs9k7nJvvaFKbWZtHkgVja9JjYUMlMA1ipS3VEItGtsSLiVR7MWUaGUC5IODe8v6XLQzLVfQRLnD8pCI0qpkqFYY+Xx27z+D6Z7dLyAulHFBxcwmt8tE+TeUVNGgpnR0JFMW/kCvqJDwsE6ZMDqxgmCLVdyQqonJzZL2Ztro29n0CRBGIyB1rxKKtHCrMqhceOACA94ab6KmJ1SZl+Jyq3SqvpsM5p2rBRMO6HHpbv5TgwIDAQAB'],
				],
			],
		'kommaer.com' => [
			'%template' => 'mono-full',
			'google-dancom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAg6jNF10CZlbKirNHVRTcQEuxWOarRjlI2Ikd9mfwFFu8+qxouHygKh1aDuKVBX365cG7mcOEfCDUmmC3y9EO1oN+GbqdI7KwqwHWyTOogfvglQjkZHWojH7t4CLH1iV0GDmP0kkiu4xJ/pjifhfFWPf/1sgMZbsnDHzxi6vBoYKftjBkUwyEVtDXjdnqUTQfekJ4UEq1L3+ro1yArG7T2K9d0x8kBLxx0hFukYNAlHC3pv171WGxce7j+Db6pHS2/Pu1KwuFU+NKCyvgQ1SBM4azzmnFNWt0B2luc085kzecZ7Ytyx/rHmSiMGB1lKJ8b7rhhhxpkZvpsYGW8TOb5wIDAQAB'],
				],
			],
		'kommaer.dk' => [
			'%template' => 'mono-full',
			'google-dancom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAg6jNF10CZlbKirNHVRTcQEuxWOarRjlI2Ikd9mfwFFu8+qxouHygKh1aDuKVBX365cG7mcOEfCDUmmC3y9EO1oN+GbqdI7KwqwHWyTOogfvglQjkZHWojH7t4CLH1iV0GDmP0kkiu4xJ/pjifhfFWPf/1sgMZbsnDHzxi6vBoYKftjBkUwyEVtDXjdnqUTQfekJ4UEq1L3+ro1yArG7T2K9d0x8kBLxx0hFukYNAlHC3pv171WGxce7j+Db6pHS2/Pu1KwuFU+NKCyvgQ1SBM4azzmnFNWt0B2luc085kzecZ7Ytyx/rHmSiMGB1lKJ8b7rhhhxpkZvpsYGW8TOb5wIDAQAB'],
				],
			],
		'kommaforslag.dk' => [
			'%template' => 'mono-full',
			'google-dancom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAg6jNF10CZlbKirNHVRTcQEuxWOarRjlI2Ikd9mfwFFu8+qxouHygKh1aDuKVBX365cG7mcOEfCDUmmC3y9EO1oN+GbqdI7KwqwHWyTOogfvglQjkZHWojH7t4CLH1iV0GDmP0kkiu4xJ/pjifhfFWPf/1sgMZbsnDHzxi6vBoYKftjBkUwyEVtDXjdnqUTQfekJ4UEq1L3+ro1yArG7T2K9d0x8kBLxx0hFukYNAlHC3pv171WGxce7j+Db6pHS2/Pu1KwuFU+NKCyvgQ1SBM4azzmnFNWt0B2luc085kzecZ7Ytyx/rHmSiMGB1lKJ8b7rhhhxpkZvpsYGW8TOb5wIDAQAB'],
				],
			],
		'kommaforslag.com' => [
			'%template' => 'mono-full',
			'google-dancom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAg6jNF10CZlbKirNHVRTcQEuxWOarRjlI2Ikd9mfwFFu8+qxouHygKh1aDuKVBX365cG7mcOEfCDUmmC3y9EO1oN+GbqdI7KwqwHWyTOogfvglQjkZHWojH7t4CLH1iV0GDmP0kkiu4xJ/pjifhfFWPf/1sgMZbsnDHzxi6vBoYKftjBkUwyEVtDXjdnqUTQfekJ4UEq1L3+ro1yArG7T2K9d0x8kBLxx0hFukYNAlHC3pv171WGxce7j+Db6pHS2/Pu1KwuFU+NKCyvgQ1SBM4azzmnFNWt0B2luc085kzecZ7Ytyx/rHmSiMGB1lKJ8b7rhhhxpkZvpsYGW8TOb5wIDAQAB'],
				],
			],
		'kommatroll.com' => [
			'%template' => 'mono-full',
			'google-deucom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqKto5SpWLvH9NCf1iwhuVXSiOn7oZgI8dmSxDa1Mv4W8s7A+GA6Gl3JUDIVmesEcLKqZ2R21VLSVNdVZgY8RxN5EPxaSDZq6NbHXEQq906YaMFfPPAN9yTmi8ocTknGuSzfG6b0+kQjziXRWbrHEoqNicff+kZs7HTd74CE1x7Pu5KPk4YNQC0rbp2WGG6eIsM+5a9QXMwAHveuZhpThWtCTi+2knVpf3pQNoXLcU8kaUdUQe4lAGeCPibA9NVeN8sjdovKGFLAN/sjCm8lZ8XddNJpw879BmCl8VheScQemkJ7ZKNWmEKv01EMD7lmnSXZsLkHfjWEOHPwLc9DVKQIDAQAB'],
				],
			],
		'ordret.com' => [
			'%template' => 'mono-full',
			],
		'retmig.com' => [
			'%template' => 'mono-full',
			'google-retmig._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAhrGYj8/5bb/k6zRaoZb8DTEMQM+rXlZrK/n5Y/xifWgXTka1V4fTANSaZRVIDZH2iCkc9pLcV0wTmH/WS68K3mLUYyxYvJit2M4XGtyFFZ1rRkJG/NOn5IKll67j7j8CYx4VuQIeAPYQNl/f5OJ4GSRI803OMlXsydBncoS4FqsJuX/JwBCMeLh3BPYzf2cpluBV/Wq6fAcGdOtwuGwlk9iQT/J7npmV5OfkaqFo8bwCiNwL7edqUI7raj8cY7kz5ISKLqLNz4YNeI/Z883TxzCuNOHaH6FCD3T7b6+xbpbiy6VHMm75fP65A/Lgswv6dLh2UoVHRYsvhGsudaxcVwIDAQAB'],
				],
			],
		'retmig.dk' => [
			'%template' => 'mono-full',
			'google-retmig._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAhrGYj8/5bb/k6zRaoZb8DTEMQM+rXlZrK/n5Y/xifWgXTka1V4fTANSaZRVIDZH2iCkc9pLcV0wTmH/WS68K3mLUYyxYvJit2M4XGtyFFZ1rRkJG/NOn5IKll67j7j8CYx4VuQIeAPYQNl/f5OJ4GSRI803OMlXsydBncoS4FqsJuX/JwBCMeLh3BPYzf2cpluBV/Wq6fAcGdOtwuGwlk9iQT/J7npmV5OfkaqFo8bwCiNwL7edqUI7raj8cY7kz5ISKLqLNz4YNeI/Z883TxzCuNOHaH6FCD3T7b6+xbpbiy6VHMm75fP65A/Lgswv6dLh2UoVHRYsvhGsudaxcVwIDAQAB'],
				],
			],
		'visl.dk' => [
			'%template' => 'mono-full',
			'%domain.' => [
				'TXT' => ['google-site-verification=lfTlhUPaCiRSQAWq1buyyZi-p7LJlwNqazLPTQr4_m8'],
				],
			'corp.%domain.' => [
				'A' => ['176.9.7.122'],
				'AAAA' => ['2a01:4f8:141:504b::2'],
				],
			'corp2.%domain.' => [
				'A' => ['88.99.217.156'],
				'AAAA' => ['2a01:4f8:10a:42a3::2'],
				],
			],
		'wikitrans.net' => [
			'%template' => 'mono-full',
			],
		],

	'tdconsult' => [
		'athia.dk' => [
			'%template' => 'wolf-mx',
			],
		'athia.eu' => [
			'%template' => 'wolf-mx',
			],
		'danish.guru' => [
			'%template' => 'mono-full', // NB: mono
			],
		'dansk.guru' => [
			'%template' => 'mono-full', // NB: mono
			],
		'didriksen.cc' => [
			'%template' => 'wolf-full',
			],
		'jervelundhaven.dk' => [
			'%template' => 'wolf-full',
			],
		'naytei.net' => [
			'%template' => 'wolf-mx',
			],
		'pjj.cc' => [
			'%template' => 'wolf-mx',
			'gitlab.%domain.' => [
				'CNAME' => ['oqaa.projectjj.com.'],
				],
			],
		'projectjj.com' => [
			'%template' => 'wolf-base',
			'%domain.' => [
				'MX' => ['1 aspmx.l.google.com.', '5 alt1.aspmx.l.google.com.', '5 alt2.aspmx.l.google.com.', '10 aspmx2.googlemail.com.', '10 aspmx3.googlemail.com.'],
				'TXT' => ['v=spf1 include:_spf.google.com a mx ptr a:oqaa.projectjj.com a:timber.projectjj.com a:wolf.projectjj.com a:oqaa.projectjj.com ip4:37.187.75.182 ip4:192.99.34.59 ip6:2001:41d0:a:2bb6::/64 ip6:2607:5300:60:493b::1/64 ~all'],
				],
			'mail.%domain.' => [
				'A' => ['5.9.18.201'],
				'AAAA' => ['2a01:4f8:160:92cf::2'],
				'TXT' => ['v=spf1 include:projectjj.com ~all'],
				],
			'oqaa.%domain.' => [
				'A' => ['144.217.254.26'],
				'AAAA' => ['2607:5300:203:101a::'],
				'TXT' => ['v=spf1 include:projectjj.com ~all'],
				],
			'wolf.%domain.' => [
				'A' => ['5.9.18.201'],
				'AAAA' => ['2a01:4f8:160:92cf::2'],
				'TXT' => ['v=spf1 include:projectjj.com ~all'],
				],
			'timber.%domain.' => [
				'A' => ['5.9.18.201'],
				'AAAA' => ['2a01:4f8:160:92cf::2'],
				'TXT' => ['v=spf1 include:projectjj.com ~all'],
				],
			'apertium.%domain.' => [
				'CNAME' => ['oqaa.%domain.'],
				],
			],
		'real-vampires.com' => [
			'%template' => 'wolf-mx',
			],
		'scaledup.life' => [
			'%template' => 'wolf-full',
			'google-scaleduplife._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAn4NDuX+WgDjn7r81ZYWfSUcyIPR3Ypp3hvPxxsHelasmyHbGNffqQScy9lB0n7sXj9P/bM84pdn7y6UmTda5plbmwEWGmq+qQFvkwt0syRR7TV6iPYBC1L27DbfoV+DkCk4ECGg2asJB5tlS8uBSIkSGOilgaVx6yMZ9vZqO3NzPi8YtM6ovi9Fkh+SXY8/wIKLduTEblLm0GqEMN3jiKO+VFP+n9sifJG0T/4FkX8T41wfztiifGlRRm8Zjt2pUgR1zD0VqijZEFORwJMDyPvZOTe1ixzbQ1bxOomhkho/4J1mBEiMEjGtLH37YXKq3pOATMBRZ6m0UCi7/FZb39wIDAQAB'],
				],
			],
		'skarby.cc' => [
			'%template' => 'wolf-full',
			'google-skarbycc._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArIOx/x8xnE6RyZK5pXsy4orl34AmIujET8aGJaFgd1kDG93XQdRA5OmdIHhSz4esZ5yCmXIQMdPhxmPdUH68Ebxk2H6allPU66sNBANY2BMznrZEr6V2o1vzO8dGDYzanBlqDtQCI3HDwL+gFsg44hJfheAWH79Qe4tCDClzS5qVHgwsqt6oTPfmY1GhusSgsQYN2UbV06WNp/KMn2jMQwljdzeWwZXwAEH+AoHwN7r6wnRjoDMdFRPBR+woXWxuZnHSEEmNFjL+aZYw/2ItzwAf22fuy6RUmhyY7B4uya8sGpls+SztC08gBAlCXxMatjvwqum3Rw+owSzeKhijFQIDAQAB'],
				],
			],
		'spoons.cc' => [
			'%template' => 'wolf-full',
			],
		'tinodidriksen.com' => [
			'%template' => 'wolf-full',
			'google-tdcom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAlz5AVOuAJZ5+/xIyw/Tyxj56/GQ/gNuLSipQ74QhgGgzVZ9xSA601TAev18+9YDU0/ivq9TzIs0Soh89w92C/GhAozkDL7H+hzWqElHUQIRKvL+bGGjZ5gucs4I9LNrOv7RBblQ+dzbPSQg13qr38DQfaYnV4fjO3kE9jhHrk6j9H9JdP5F0714sWeTTDZBlVD6dJK8iFnk4WpOH0WrwjmxdL0McpiWJSvc6A2LoltQhHFGZuguQSErI5KH8a0rFXsdT5rNQ660ygWfI4W4zBVep+bZFuMHtyr87ZkWgCBYFtWqgaMVDnBMSZbg3LIY7jgX+rV5KzVz70/RYz8sw8QIDAQAB'],
				],
			],
		'tinodidriksen.eu' => [
			'%template' => 'wolf-full',
			],
		'vikideboradidriksen.dk' => [
			'%template' => 'wolf-full',
			],
		'örva.dk' => [
			'%template' => 'wolf-mx',
			],
		],

	'learngreenlandic' => [
		'learn.gl' => [
			'%template' => 'wolf-full',
			'google-lggl._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjgTz1+9azGpICbEqt7xvJFXsOt7yhZZ6Hd4DmZAQbVOr/aTpBarmZB8cLNe1t1U4hfwR+NgT/t8pWFiYAlINdejRebpK0riTfSFNZB/XpjKH0A+hDGpmo78Q5aR4/p5wckdV7YB9J51oO4UQEKf/JtYLtS7Q0S+3pSNq8Xecw03I25uSFEGFYKDjFPeomSmZU7PT3gOcfSeRKcSIrXYddRlGuRb+f9J9JRf+JUO4JV2FAjnN8RT6LREjEEOl3emlpovexJdOpf12E5LbiYBDKu+IlEE2b6mhbc4YZt63zKii20rCywOidE7rzEPE/+279M3GNr+1HpULriNCWfmAkQIDAQAB'],
				],
			],
		'learngreenlandic.com' => [
			'%template' => 'wolf-full',
			'google-lgcom._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgfM6OPvluOqxlj1Mw1srwCKhVhtTKVi4zDaEWFetGupwujWqWAYeSBjXDtrqQdiZv9lqK0E7qny+DVUfBzsvFagLUI2fmm+GujC+JBrk1yIkPLIj4AGnk1f6QDP+CzHlcvEuuUJBbJb96r1tDv2O3Py3DI9ly6aUnNyGqtUiVNfY4+udBAzrn0KWANOzkCKbpVRga1YsNe2HGMj8enzhSpZiR9ZvxNosynGXA0FRdzqFxLyzubDUP8hla3rewUgWiIOLDFvONqDqPw3YImbSmWr8QSW2FRKQQa90NJAdhywaPQKNhmiYBpM/spipnjbpEy9SEkNs5yYaNdZ9V/AOzQIDAQAB'],
				],
			],
		],

	'apertium-gcp' => [
		'apertium.com' => [
			'%template' => 'apertium-base',
			],
		'apertium.org' => [
			'%template' => 'apertium-base',
			],
		],

	'oqaasileriffik' => [
		'daka.gl' => [
			'%template' => 'oqaa-base',
			],
		'kukkuniiaat.gl' => [
			'%template' => 'oqaa-base',
			'%domain.' => [
				'TXT' => ['google-site-verification=1-XgEuSVsrYnFxq9kWebGSk8XLtX9sv-GOFidTvacXo'],
				],
			],
		'nutserut.gl' => [
			'%template' => 'oqaa-base',
			],
		'oqaasileriffik.gl' => [
			'%template' => 'oqaa-base',
			'%domain.' => [
				'TXT' => ['google-site-verification=qvmNd_Us1qVga-7YcrFBvWosB0icKMsQnYVEZt2RS4o'],
				],
			'_github-challenge-oqaasileriffik.%domain.' => [
				'TXT' => ['51eaa8cda6'],
				],
			'google-oqaa._domainkey.%domain.' => [
				'TXT' => ['v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAhOxgoSikr7IAUNsNFQGL2mKdbHwt/dRBtRpFaFgAVLiilidvkB31gOe62cRicab0z7DJb9RZlCpq5Cczz71hmJqBul3XNe09ku6loT/gEfxPb37Z0iJR9rkkK7rz+c9ltYgMJ1pgTXOEfSwBGW0R5LI0yudhZKGOX7AnVAjAGn88LiREQvW/rINgWbZY9JUP9qeCoQ47g3eysdVA1ZjhAi7Dz/S1TMSrKnylMAO6q55Blad+sT1iaf+yJDymUn56Zx4hk2LdJ9XuHetMZrvcs1SyZYw746SEC58n24MyBNH8y26L5cgY6xB22lefv0HBYniOiRuGBFUQmWRNP/p0jQIDAQAB'],
				],
			],
		'oqaatsit.gl' => [
			'%template' => 'oqaa-base',
			],
		'ordbog.gl' => [
			'%template' => 'oqaa-base',
			],
		'taaguutit.gl' => [
			'%template' => 'oqaa-base',
			],
		'nunataqqi.gl' => [
			'%template' => 'oqaa-base',
			],
		],
	];

shell_exec('mkdir -p gcloud-dns-zones');
foreach ($GLOBALS['-projects'] as $p => $ds) {
	echo "# Project: {$p}\n";
	gcloud_dns($p, $ds);
}
