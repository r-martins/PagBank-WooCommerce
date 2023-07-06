<?php
class OrderMock
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function get_billing_first_name(): string
    {
        return $this->data['billing']['first_name'];
    }

    public function get_billing_last_name(): string
    {
        return $this->data['billing']['last_name'];
    }

    public function get_meta(string $key)
    {
        foreach ($this->data['meta_data'] as $meta) {
            if ($meta['key'] === $key) {
                return $meta['value'];
            }
        }

        return null;
    }

    // Adicione outros getters conforme necessário para os campos desejados
}
