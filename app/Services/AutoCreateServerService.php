<?php

namespace App\Services;

use App\Models\Cluster;
use Illuminate\Support\Str;
use RenokiCo\PhpK8s\Kinds\K8sResource;
use RenokiCo\PhpK8s\KubernetesCluster;

class AutoCreateServerService
{
    protected $cluster;

    public function createServer($domain, Cluster $cluster, $params = [])
    {
        $k8sCluster = $this->getK8sCluster($cluster);
        $slug = Str::slug(strtr($domain, ['.' => '-']));
        $variables = self::prepareReplacement($cluster->settings ?? []);
        $services = $k8sCluster->fromYaml($this->getServerConfig([
            ...$variables,
            '{server_name}' => $slug,
            '{CHANNEL_SYNC_API_VALUE}' => $params['channel_sync_url'] ?? '',
            '{REDIS_HOST_VALUE}' => $params['redis_host'] ?? 'localhost',
            '{REDIS_PORT_VALUE}' => $params['redis_port'] ?? '6379',
        ]));
        foreach ($services as $service) {
            $service->create();
        }
    }

    public function updateAlb($domains, Cluster $cluster)
    {
        $k8sCluster = $this->getK8sCluster($cluster);
        $ingress = $k8sCluster->fromYaml(
            $this->getAlbConfig($domains, self::prepareReplacement($cluster->settings ?? []))
        );
        $ingress->createOrUpdate();
    }

    protected function getK8sCluster(Cluster $cluster)
    {
        if ($this->cluster === null) {
            $settings = $cluster->settings ?? [
                'url' => null,
                'cluster_name' => null,
                'namespace' => null,
            ];
            $cluster = KubernetesCluster::fromUrl($settings['url']);
            $cluster->withToken($this->getToken($cluster->region, $settings['cluster_name']));
            K8sResource::setDefaultNamespace($settings['namespace']);
            $this->cluster = $cluster->withoutSslChecks();
        }
        return $this->cluster;
    }

    protected function getServerConfig($params = [])
    {
        return strtr(
            file_get_contents(config_path('servers/server_template.yaml')),
            $params
        );
    }

    protected function getAlbConfig($domains, $params = [])
    {
        $yaml = yaml_parse_file(strtr(config_path('servers/alb.yaml'), $params));
        $yaml['spec']['rules'] = [];
        foreach ($domains as $domain) {
            $slug = Str::slug(strtr($domain, ['.' => '-']));
            $yaml['spec']['rules'][] = [
                'host' => $domain,
                'http' => [
                    'paths' => [
                        [
                            'path' => '/',
                            'pathType' => 'Prefix',
                            'backend' => [
                                "service" => [
                                    'name' => $slug . '-service',
                                    'port' => [
                                        'number' => 6001
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        return yaml_emit($yaml);
    }

    protected function getToken($region, $clusterName)
    {
        $expiry = Carbon::now()->addMinutes(15);
        $request = new Request('GET', "https://sts.{$region}.amazonaws.com/?Action=GetCallerIdentity&Version=2011-06-15", [
            'x-k8s-aws-id' => $clusterName,
        ]);
        $signer = new SignatureV4('sts', $region, []);
        $credentialsProvider = CredentialProvider::defaultProvider()()->wait();
        $signature = $signer->presign($request, $credentialsProvider, $expiry);
        // @see https://github.com/aws/aws-cli/commit/3ef2a3cf895cb64cf45a28284ca3291cd1c33755
        return 'k8s-aws-v1.' . rtrim(base64_encode($signature->getUri()), '=');

    }

    protected static function prepareReplacement(array $params)
    {
        return array_combine(array_map(fn ($key) => '{' . $key . '}' , array_keys($params)), array_values($params));
    }
}
