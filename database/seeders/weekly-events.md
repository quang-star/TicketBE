# Weekly event sample data

`WeeklyEventsSeeder` adds 100 fictional listings across ten upcoming weekends,
with ten listings per weekend. The event names are fictional and the times and
shared venue are sample data. Each description identifies the listing as sample
data; do not treat these listings as real events or sell their tickets.

The seeder expects the four event categories, a venue, and the four sample
organizers created by `UpcomingEventsSeeder` to exist. Review that seeder before
running it: it also creates orders and sample accounts. This seeder does not
create accounts or orders. Set `APP_URL` to the correct public backend origin.

Run explicitly in the intended environment:

```sh
php artisan db:seed --class=WeeklyEventsSeeder
```

Existing events with the same name and sample organizer are left untouched;
rerunning does not move their schedules or replenish tickets. A single database
transaction covers newly added rows. Images are committed under
`public/images/weekly-events`. Source, author, and license for each image are
listed in `weekly-image-credits.json` and in the corresponding event description.
