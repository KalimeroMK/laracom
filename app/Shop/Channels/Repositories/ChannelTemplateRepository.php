<?php

namespace App\Shop\Channels\Repositories;

use App\Shop\Base\BaseRepository;
use App\Shop\Channels\Exceptions\ChannelInvalidArgumentException;
use App\Shop\Channels\Exceptions\ChannelNotFoundException;
use App\Shop\Channels\ChannelTemplate;
use App\Shop\Channels\Repositories\Interfaces\ChannelRepositoryInterface;
use App\Shop\Products\Product;
use App\Shop\Channels\Transformations\ChannelTransformable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use App\Shop\Channels\Channel;
use App\Shop\Channels\Repositories\Interfaces\ChannelTemplateRepositoryInterface;

class ChannelTemplateRepository extends BaseRepository implements ChannelTemplateRepositoryInterface {

    use ChannelTransformable;

    /**
     * ChannelRepository constructor.
     * @param Channel $channel
     */
    public function __construct(ChannelTemplate $channelTemplate) {
        parent::__construct($channelTemplate);
        $this->model = $channelTemplate;
    }

    /**
     * Create the channel
     *
     * @param array $params
     * @return Channel
     */
    public function createChannelTemplate(array $params): ChannelTemplate {

        try {
            $channelTemplate = new ChannelTemplate($params);
            $channelTemplate->save();
            return $channelTemplate;
        } catch (QueryException $e) {
            throw \Exception($e->getMessage());
        }
    }

    /**
     * Update the channel
     *
     * @param array $data
     *
     * @return bool
     * @throws ChannelInvalidArgumentException
     */
    public function updateChannelTemplate(array $data): bool {
        try {
            return $this->model->where('id', $this->model->id)->update($data);
        } catch (QueryException $e) {
            throw new \Exception($e);
        }
    }

    public function updateOrCreate($data, $params) {

        // If there's a flight from Oakland to San Diego, set the price to $99.
        // If no matching model exists, create one.
        $template = $this->model->updateOrCreate(
                $params, $data
        );
    }

    public function getTemplatesForChannel(Channel $channel) {
        return $this->model->where('channel_id', $channel->id)->get()->keyBy('section_id');
    }

    /**
     * 
     * @param Channel $channel
     * @param int $sectionId
     * @return type
     */
    public function getTemplateForChannelBySection(Channel $channel, int $sectionId) {
        return $this->model
                        ->where('channel_id', $channel->id)
                        ->where('section_id', $sectionId)
                        ->get()
                        ->first();
    }

}
