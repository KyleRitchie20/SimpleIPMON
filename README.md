# SimpleIPMON
Simple IP Monitor

## To Install : 
1.git Clone into web dir example: ```/var/www/html/ip```

2.In the install Directory run: ```composer install``` 

3. copy .env.example to .env file and edit the .env file to include database details (I used Postgresql, Make sure it is installed and configured)

4. in the install dir run ```php artisan key:generate``` run ```php artisan migrate``` run ```npm install``` run ```npm run dev```(Note that this npm service needs to be setup, I recommend using PM2 service manager) > TODO add instructions for PM2 setup

6 add default user: run ```php artisan tinker``` then ```$user = App\Models\User::create(['name' => 'Admin','email' => 'admin@ip-management.com','email_verified_at' => now(),'password' => bcrypt('password')
]);``` This adds the default user in the database to get into the app, You can add any users manually like this.



