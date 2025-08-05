import { numberPadding, formatDate } from "../../../public/js/helpers/DateHelpers.js"

export default {
	props: {
			event: Object,
		},
		data() {
			return {
	
			}
		},
		computed: {
			lektorenLinks: function () {
				if (!this.event || !Array.isArray(this.event.lektor) || !this.event.lektor.length) return "a";
	
				let lektorenLinks = {};
				this.event.lektor.forEach((lektor) => {
					lektorenLinks[lektor.kurzbz] = FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + `/Cis/Profil/View/${lektor.mitarbeiter_uid}`;
				})
				return lektorenLinks;
			},
			getOrtContentLink: function () {
				if (!this.event || !this.event.ort_content_id) return "a";
	
				return FHC_JS_DATA_STORAGE_OBJECT.app_root + FHC_JS_DATA_STORAGE_OBJECT.ci_router + `/CisVue/Cms/content/${this.event.ort_content_id}`
			},
			start_time: function () {
			if (!this.event.beginn)
				return 'N/A';
			if (!(this.event.beginn instanceof Date)) {
				return this.event.beginn;
				}
			return numberPadding(this.event.beginn.getHours()) + ":" + numberPadding(this.event.beginn.getMinutes());
			},
			end_time: function () {
			if (!this.event.ende)
				return 'N/A';
			if (!(this.event.ende instanceof Date)) {
				return this.event.ende;
				}
			return numberPadding(this.event.ende.getHours()) + ":" + numberPadding(this.event.ende.getMinutes());
			}
		},
		methods: {
			mehtodNumberPadding: function (number) {
				return numberPadding(number);
			},
		methodFormatDate: function (d) {
			return formatDate(d);
			},
		},
	template: `
		<h3>
				{{$p.t('lvinfo','Moodleinformationen')}}
			</h3>
			<table class="table table-hover mb-4">

				<tbody>
					<tr>
						<th>{{
							$p.t('global','datum')?
							$p.t('global','datum')+':'
							:''
						}}</th>
						<td>{{methodFormatDate(event.datum)}}</td>
					</tr>
					<tr>
						<th>{{$p.t('fristenmanagement','frist')}}:</th>
						<td>{{start_time}}</td>
					</tr>
					<tr>
						<th>{{$p.t('global','aktivitaet')}}:</th>
						<td v-html="event?.assignment"></td>
					</tr>
					<tr>
						<th>{{$p.t('global','typ')}}:</th>
						<td><img v-if="event?.activityIcon" class="me-1 fhc-tertiary" :src="event?.activityIcon" />{{event?.purpose}}</td>
					</tr>
					<tr v-if="event?.actionname">
						<th>{{$p.t('lvinfo','actionname')}}:</th>
						<td>
							{{event?.actionname}}
						</td>
					</tr>
					<tr v-if="event?.overdue">
						<th>{{$p.t('lvinfo','overdue')}}:</th>
						<td>
							{{$p.t('lvinfo','overdueEvent')}}
						</td>
					</tr>
					<tr >
				    	<th>{{$p.t('lvinfo','moodleLink')}}</th>
						<td>
							<a :href="event?.url" target="_blank" :aria-label="$p.t('lvinfo','moodleLink')" :title="$p.t('lvinfo','moodleLink')" ><i class="fa fa-arrow-up-right-from-square me-1" aria-hidden="true"></i></a>
						</td>
					</tr>
				</tbody>
			</table>`,
};
