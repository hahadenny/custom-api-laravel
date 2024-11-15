<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected function getBaseAppUrl() : string
    {
        $urlPieces = parse_url(config('app.url'));
        $userAndPass = isset($urlPieces['user'])
            ? $urlPieces['user'] . (isset($urlPieces['pass']) ? ':' . $urlPieces['pass'] : '') . '@'
            : '';
        $path = $urlPieces['path'] ?? '';
        return $urlPieces['scheme'] . '://' . $userAndPass . $urlPieces['host'] . $path;
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $socketConnName = config('services.socketio.on-prem.default');
        $socketConnConfig = config('services.socketio.on-prem.connections.'.$socketConnName);
        return [
            'name'        => $this->faker->company(),
            'description' => $this->faker->paragraph(1),
            'ue_url'      => 'http://'
                . rtrim($socketConnConfig['host'], '/')
                . ':'
                . $socketConnConfig['port'],
                //'http://localhost:6001',
            'is_active'   => 1,
        ];
    }

    /**
     * Give the model a specified socket server url
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function socket(string $url)
    {
        return $this->state(function (array $attributes) use ($url) {
            return [
                'ue_url' => $url,
            ];
        });
    }

    /**
     * Set attrs for an on-prem server company
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function onPrem()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Main Company',
                'description' => '',
            ];
        });
    }

    /**
     * Set attrs for a local server company
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function local()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Local Company',
                'description' => '',
            ];
        });
    }

    /**
     * Set attrs for a dev server company
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function dev()
    {
        return $this->state(function (array $attributes) {
                        return [
                            'name' => 'Dev Company',
                            'description' => '',
                        ];
                    })
                    ->socket('sio.porta.solutions');
    }

    /**
     * Set attrs for a staging server company
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function staging()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Staging Company',
                'description' => '',
            ];
        })
                    ->socket('siostaging.porta.solutions');
    }

    /**
     * Set attrs for a production server company
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function prod()
    {
        return $this->state(function (array $attributes) {
                        return [
                            'name' => 'Porta Prod Company',
                            'description' => '',
                        ];
                    })
                    ->socket('sioprod.porta.solutions');
    }

}
