<?php

/*
	View Index
*/
Route::get(array('/', '/(:any)'), function($page = 1) {
	Registry::set('categories', new Items(Category::all()));

	$user = Auth::user();
	$perpage = 10;

	$query = Query::table(Discussion::$table);
	$get = array('discussions.*');

	if($user) {
		$get[] = 'user_discussions.viewed';

		$query->left_join('user_discussions', 'user_discussions.discussion', '=', 'discussions.id')
			->where('user_discussions.user', '=', $user->id)
			->or_where_is_null('user_discussions.user');
	}

	$discussions = $query->take($perpage)->skip(--$page * $perpage)
			->order_by('votes', 'desc')->order_by('lastpost', 'desc')
			->get($get);

	Registry::set('discussions', new Items($discussions));
	
	return new Template('index');
});

/*
	View category
*/
Route::get(array('category/(:any)', 'category/(:any)/(:num)'), function($slug, $page = 1) {
	if( ! $category = Category::slug($slug)) {
		return Response::error(404);
	}

	Registry::set('categories', new Items(Category::all()));
	Registry::set('category', $category);
	
	$perpage = 10;

	$discussions = Discussion::where('category', '=', $category->id)
		->order_by('votes', 'desc')
		->order_by('lastpost', 'desc')
		->take($perpage)
		->skip(--$page * $perpage)
		->get();
		
	Registry::set('discussions', new Items($discussions));
	
	return new Template('category');
});

/*
	Login
*/
Route::get('login', function() {
	return new Template('login');
});

Route::post('login', function() {
	if( ! Auth::attempt(Input::get('username'), Input::get('password'))) {
		Input::flash();
		
		Notify::error('Invalid details');

		return Response::redirect('login');
	}

	return Response::redirect('/');
});

/*
	Logout
*/
Route::get('logout', function() {
	Auth::logout();

	return Response::redirect('/');
});

/*
	Register
*/
Route::get('register', function() {
	return new Template('register');
});

Route::post('register', function() {
	$input = array(
		'name' => Input::get('name'), 
		'email' => Input::get('email'),
		'username' => Input::get('username'), 
		'password' => Input::get('password')
	);

	$validator = new Validator($input);

	$validator->check('name')
		->is_max(3, 'Please enter your name');

	$validator->check('email')
		->is_email('Please enter your email address');

	$validator->add('unquie_username', function($str) {
		$user = User::search(array('username' => $str));

		return ! isset($user->id);
	});

	$validator->check('username')
		->is_unquie_username('Username is already taken')
		->is_max(5, 'Please enter a username');

	$validator->check('password')
		->is_max(6, 'Please enter a secure password');

	if($errors = $validator->errors()) {
		Input::flash();

		Notify::error($errors);

		return Response::redirect('register');
	}

	User::create(array(
		'role' => 'user',
		'registered' => date('c'),
		'name' => $input['name'],
		'email' => $input['email'],
		'username' => $input['username'],
		'password' => Hash::make($input['password'])
	));

	$user = User::search(array('username' => $input['username']));

	Session::put(Auth::$session, $user);

	Notify::success('Your account has been created');

	return Response::redirect('/');
});

/*
	404 catch all
*/
Route::any('*', function() {
	return Response::error(404);
});