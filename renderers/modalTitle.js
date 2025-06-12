
export default {
	props: {
		event: {
			type: Object,
			required: true,
		}
	},
	template: /*html*/ `
	<strong v-html="event.titel"></strong>`,
};
