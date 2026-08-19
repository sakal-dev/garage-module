<template>
	<inertia-head :title="__('Edit Vehicle')" />
	<vee-form :validation-schema="schema" @submit.prevent="submitCallback" v-slot="{ meta, setErrors }" :initial-values="initialFormData">
		<sakal-form-modal size="lg">
			<template #title>{{ __("Edit Vehicle") }}</template>
			<vehicle-form v-model="item"></vehicle-form>
			<template #footer>
				<Button type="submit" :disabled="!meta.valid || item.processing" @click.prevent="submitCallback(setErrors)">
					<Loader2 v-if="item.processing" class="tw-mr-2 tw-h-4 tw-w-4 tw-animate-spin" />
					{{ __('Update') }}
				</Button>
			</template>
		</sakal-form-modal>
	</vee-form>
</template>

<script setup>
	import { useForm } from "@inertiajs/vue3";
	import VehicleForm from "../../../Components/Dashboard/VehicleForm.vue";
	import { Button } from "@/Components/ui/button";
	import { Loader2 } from "lucide-vue-next";
	import { toTypedSchema } from "@vee-validate/zod";
	import * as z from "zod";

	const schema = toTypedSchema(z.object({
		customer_id: z.number({ error: __('Customer is required') }),
		plate_number: z.union([z.string(), z.null()]).optional(),
		make: z.union([z.string(), z.null()]).optional(),
		model: z.union([z.string(), z.null()]).optional(),
		color: z.union([z.string(), z.null()]).optional(),
		year: z.union([z.number(), z.string(), z.null()]).optional(),
		notes: z.union([z.string(), z.null()]).optional(),
	}));

	const props = defineProps({
		vehicle: {
			type: Object,
			required: true
		}
	});

	const initialFormData = {
		...props.vehicle,
	};

	const item = useForm(initialFormData);

	const submitCallback = (setErrors) => {
		item.put(route("dashboard.garage.vehicles.update", {
			vehicle: props.vehicle.uuid,
		}), {
			preserveScroll: true,
			preserveState: true,
			onError: (errors) => {
				if (setErrors) {
					setErrors(errors);
				}
			},
		});
	};
</script>
