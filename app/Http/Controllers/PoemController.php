<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Models\PoemRating;
use DB;
use Illuminate\Http\Request;
use Mail;
use Validator;

class PoemController extends Controller
{

	protected $formRules = [
		'user_name' => 'honeypot',
		'user_time' => 'required|honeytime:1',
		'poem_title' => 'required',
		'poem_text' => 'required',
	];

	protected $formMessages = [
		'honeypot' => 'Vi tror du er en robot',
		'user_time.honeytime' => 'Vi tror du er en robot',
		'poem_title.required' => 'Diktet må ha en tittel',
		'poem_text.required' => 'Diktet må ha en tekst',
	];

	public function formSubmit(Request $request)
	{
		$validator = Validator::make(
			$request->all(),
			$this->formRules,
			$this->formMessages
		);

		if ($validator->fails()) {
			return redirect()
				->route('frontpage')
				->withInput($request->only('poem_title', 'poem_text'))
				->withErrors($validator, 'poem');
		}

		$poemTitle = htmlentities($request->input('poem_title'));
		$poemText = htmlentities($request->input('poem_text'));

		$poem = new Poem();
		$poem->title = $poemTitle;
		$poem->text = $poemText;
		$poem->save();

		$emailData = [
			'title' => $poemTitle,
			'text' => preg_replace('/\r\n|\r|\n/', '<br>', $poemText),
		];
		Mail::send('emails.poem', $emailData, function($message)
		{
			$message
				->subject('Dikt')
				->from($_ENV['poem_email'], 'Diktkassa')
				->to($_ENV['poem_email'], 'Diktkassa');
		});

		return redirect()->route('frontpage')->with('message', 'Oi - hva har hendt? <br> Jo: ditt dikt er sendt!');
	}

	public static function getViewFriendlyPoem($poemId)
	{
		$poem = Poem::find($poemId);
		$poemRating = $poem['rating'];
		$poem['rating_markup_class_value'] = str_replace('.', '-', round($poemRating * 2) / 2);
		$poem['rating_markup_display_value'] = (
			$poemRating > 0
			? str_replace('.', ',', round($poemRating * 2) / 2)
			: ''
		);
		$poem['text'] = preg_replace('/^(.+)$/m', '<span class="poem-line">$1<br></span>', $poem['text']);

		return $poem;
	}

	protected static function getRandomPoem(Request $request, $randomMode, array $poemIds)
	{
		//	To avoid getting the same poems repeatedly, store the last viewed
		//	poems in session and exclude them from the random pool.
		$session = $request->session();
		$sessionPoemTrailKey = 'last_viewed_poem_ids_' . $randomMode;
		$viewedPoemTrailLength = min(20, max(0, count($poemIds) - 1));
		$lastViewedPoemIds = array_slice($session->get($sessionPoemTrailKey, []), 0, $viewedPoemTrailLength);
		$randomPoemIdsPool = array_values(array_diff($poemIds, $lastViewedPoemIds));
		$randomPoemPoolIndex = mt_rand(0,max(0, count($randomPoemIdsPool) - 1));
		$randomPoemId = $randomPoemIdsPool[$randomPoemPoolIndex];
		array_unshift($lastViewedPoemIds, $randomPoemId);
		$session->set($sessionPoemTrailKey, $lastViewedPoemIds);

		return self::getViewFriendlyPoem($randomPoemId);
	}

	public static function getRandomPoemAll(Request $request)
	{
		$poemTableName = with(new Poem)->getTable();
		$allPoemIds = DB
			::table($poemTableName)
			->lists('id');
		return self::getRandomPoem($request, 'all', $allPoemIds);
	}

	public static function getRandomPoemLatest(Request $request)
	{
		$poemTableName = with(new Poem)->getTable();
		$poemIds = DB
			::table($poemTableName)
			->orderBy('created_at', 'desc')
			->take(20)
			->lists('id');
		return self::getRandomPoem($request, 'latest', $poemIds);
	}

	public static function getRandomPoemHighestRated(Request $request)
	{
		$poemTableName = with(new Poem)->getTable();
		$poemIds = DB
			::table($poemTableName)
			->orderBy('rating', 'desc')
			->take(20)
			->lists('id');
		return self::getRandomPoem($request, 'highest_rated', $poemIds);
	}

	public static function getPoemCount()
	{
		return Poem::count();
	}

	/**
	 * @param  numeric $poemId
	 * @param  string $fingerprint
	 * @return object
	 */
	public static function jsonGetPoemRateInfo(Request $request, $poemId = '', $fingerprint = '')
	{
		$poemId = $request->input('id', $poemId);
		$fingerprint = $request->input('fingerprint', $fingerprint);

		$url = '';
		$fingerprintIsUsed = false;
		$rating = null;
		if (ctype_digit($poemId) && trim($fingerprint) !== '') {
			$url = route('jsonPostPoemRate', [], false);
			$fingerprintIsUsed = self::isFingerprintUsed($poemId, $fingerprint);
			if ($fingerprintIsUsed) {
				$rating = PoemRating
					::where('fingerprint', '=', $fingerprint)
					->where('poem_id', '=', $poemId)
					->first()
					->rating;
			}
		}
		return response()->json([
			'url' => $url,
			'has_rated' => $fingerprintIsUsed,
			'rating' => $rating,
		]);
	}

	/**
	 * @param  Request $request
	 * @param  numeric $poemId
	 * @param  string $fingerprint
	 * @param  numeric $rating
	 * @return object
	 */
	public static function jsonPostPoemRate(
		Request $request,
		$poemId = '',
		$fingerprint = '',
		$rating = ''
	)
	{
		$poemId = $request->input('id', $poemId);
		$fingerprint = $request->input('fingerprint', $fingerprint);
		$rating = $request->input('rating', $rating);

		$error = [];
		$success = [];

		if (
			ctype_digit($poemId)
			&& trim($fingerprint) !== ''
			&& is_numeric($rating)
		) {
			if (self::isFingerprintUsed($poemId, $fingerprint)) {
				$success[] = 'Poem rating was updated.';
				$poemRating = PoemRating
					::where('fingerprint', '=', $fingerprint)
					->where('poem_id', '=', $poemId)
					->first();
			} else {
				$success[] = 'Poem was rated.';
				$poemRating = new PoemRating();
				$poemRating->fingerprint = $fingerprint;
				$poemRating->poem_id = $poemId;
			}
			$poemRating->rating = $rating;
			$poemRating->save();
			$rating = self::updatePoemRating($poemId);
			$rating = str_replace('.', ',', floor($rating * 2) / 2);
		} else {
			$error[] = 'Invalid input.';
		}

		return response()->json([
			'error' => $error,
			'success' => $success,
			'rating' => $rating,
		]);
	}

	/**
	 * @param  numeric $poemId
	 * @param  string $fingerprint
	 * @return boolean
	 */
	protected static function isFingerprintUsed($poemId, $fingerprint)
	{
		$poemMatch = PoemRating
			::where('fingerprint', '=', $fingerprint)
			->where('poem_id', '=', $poemId)
			->count();
		return 0 !== $poemMatch;
	}

	/**
	 * @param  integer $poemId
	 * @return float
	 */
	protected static function updatePoemRating($poemId)
	{
		$rating = PoemRating
			::where('poem_id', '=', $poemId)
			->avg('rating');
		$poem = Poem
			::where('id', '=', $poemId)
			->first();
		$poem->rating = $rating;
		$poem->save();
		return $rating;
	}

}
