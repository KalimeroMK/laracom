namespace App\UserSearch;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ChannelPriceSearch
{
   const MODEL = App\Shop\ChannelPrices\ChannelPrice;

    public static function apply(Request $filters)
    {
        $query = 
            static::applyDecoratorsFromRequest(
                $filters, (new User)->newQuery()
            );

        return static::getResults($query);
    }
    
    private static function applyDecoratorsFromRequest(Request $request, Builder $query)
    {
        foreach ($request->all() as $filterName => $value) {

            $decorator = static::createFilterDecorator($filterName);

            if (static::isValidDecorator($decorator)) {
                $query = $decorator::apply($query, $value);
            }

        }
        return $query;
    }
    
    private static function createFilterDecorator($name)
    {
        return return __NAMESPACE__ . '\\Filters\\' . 
            str_replace(' ', '', 
                ucwords(str_replace('_', ' ', $name)));
    }
    
    private static function isValidDecorator($decorator)
    {
        return class_exists($decorator);
    }

    private static function getResults(Builder $query)
    {
        return $query->get();
    }

}
